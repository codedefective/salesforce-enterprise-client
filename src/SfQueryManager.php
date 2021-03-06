<?php

namespace Codedefective\SalesforceEnterpriseClient;

use Illuminate\Support\Collection;
use QueryResult;
class SfQueryManager
{
    private string $from;
    private array $columns = [];
    private array $conditions = [];
    private string|null $ordering = null;
    private string|null $grouping = null;
    private int $limit = 0;


    public function __construct()
    {
        return $this;
    }


    /**
     * @param $from
     * @return $this
     */
    public function from($from): static
    {
        $this->from = trim($from);
        return $this;
    }


    /**
     * @param string|array $columns
     * @return $this
     */
    public function select(string|array $columns): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * @param string $column
     * @param string|int|float|bool $value
     * @param string $operator
     * @param bool $bracket
     * @return $this
     */
    public function where(string $column, string|int|float|bool|NULL $value, string $operator='=', $bracket=false): static
    {
        array_push($this->conditions, ['bracket' => $bracket ,'column' => trim($column), 'value' => (is_string($value) ? trim($value) : $value), 'operator' => trim($operator)]);
        return $this;
    }

    /**
     * @param string $column
     * @param string|int|float|bool $value
     * @param string $operator
     * @param bool $bracket
     * @return $this
     */
    public function orWhere(string $column, string|int|float|bool|NULL $value, string $operator='=', $bracket=false): static
    {
        array_push($this->conditions, ['isOr' => true, 'bracket' => $bracket, 'column' => trim($column), 'value' => (is_string($value) ? trim($value) : $value), 'operator' => trim($operator)]);
        return $this;
    }

    /**
     * @param string $column
     * @param string $sort
     * @return $this
     */
    public function orderBy(string $column, string $sort='ASC'): static
    {
        $this->ordering = 'ORDER BY '. PHP_EOL . "\t" .  $column . ' '. $sort . ' ';
        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function groupBy(string $column): static
    {
        $this->grouping = 'GROUP BY '. PHP_EOL . "\t" .  $column . ' ';
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return string
     */
    private function generateQuery(): string
    {
        $q=  'Select '. PHP_EOL . "\t";
        $q.= trim(implode(', ',$this->columns)) . ' '. PHP_EOL;
        $q.='From '. PHP_EOL;
        $q.="\t" . $this->from . ' ' . PHP_EOL;
        $q.= $this->getWhere() . ' '. PHP_EOL;
        $q.= !is_null($this->grouping) ? $this->grouping . PHP_EOL : '';
        $q.= !is_null($this->ordering) ? $this->ordering . PHP_EOL : '';
        $q.= $this->limit > 0 ? 'Limit ' .PHP_EOL . "\t" . $this->limit . ' ' . PHP_EOL : '';
        return trim($q);
    }

    /**
     * @return string
     */
    private function getWhere(): string
    {
        $q = '';
        if (!empty($this->conditions)){
            $q.='Where ' . PHP_EOL;
            $q.=  "\t";
            foreach ($this->conditions as $conditionKey =>  $condition) {
                $condition['value'] = $this->validateConditionValue($condition['value'],$condition['operator']);
                $bracketType = !isset($condition['bracket']) || !$condition['bracket'] ?  false :  ($condition['bracket'] === '(' ? 'start' : ($condition['bracket'] === ')' ? 'stop' : false));
                $q.= isset($condition['isOr']) ? ' OR ' : '';
                $q.= $bracketType === 'start' ? '( ' : '';
                if ($condition['operator'] === 'NOT LIKE') {
                    $condition['column'] = '( NOT ' . $condition['column'];
                    $condition['operator'] = 'LIKE';
                    $condition['value'] = $condition['value'] . ' )';
                }
                $q.= ($condition['column'] . ' ' . $condition['operator'] . ' ' . $condition['value']);
                $q.= $bracketType === 'stop' ? ' )' : '';
                if (($conditionKey+1) != count($this->conditions) && !isset($this->conditions[$conditionKey+1]['isOr'])){
                    $q.=' and ' . PHP_EOL  . "\t";
                }
            }
        }
        return $q;
    }


    /**
     * @param $value
     * @param $operator
     * @return mixed
     */
    private function validateConditionValue($value,$operator):mixed{

        $sfVariables = [
            'false', 'true', 'THIS_MONTH', 'TODAY', 'THIS_YEAR' ,'YESTERDAY','TOMORROW','LAST_WEEK','THIS_WEEK','NEXT_WEEK','LAST_MONTH','NEXT_MONTH',
            'LAST_QUARTER', 'NEXT_QUARTER', 'LAST_YEAR', 'NEXT_YEAR', 'THIS_FISCAL_QUARTER', 'LAST_FISCAL_QUARTER', 'NEXT_FISCAL_QUARTER', 'THIS_FISCAL_YEAR',
            'LAST_FISCAL_YEAR', 'NEXT_FISCAL_YEAR', 'LAST_90_DAYS', 'NEXT_90_DAYS'
        ];
        $sfVariablesNDays = [
            'LAST_N_DAYS:', 'NEXT_N_DAYS:', 'NEXT_N_WEEKS:', 'LAST_N_WEEKS:', 'NEXT_N_MONTHS:', 'LAST_N_MONTHS:', 'NEXT_N_QUARTERS:', 'LAST_N_QUARTERS:',
            'NEXT_N_YEARS:', 'LAST_N_YEARS:', 'NEXT_N_FISCAL_QUARTERS:','LAST_N_FISCAL_QUARTERS:', 'NEXT_N_FISCAL_YEARS:', 'LAST_N_FISCAL_YEARS:'
        ];
        if (in_array($value,$sfVariables) || strpos_array($value,$sfVariablesNDays)){
            return $value;
        }

        if (!isDate($value) && is_string($value)) $value = "'".$value."'";
        if (isDate($value) && ($operator === '>' || $operator === '>=' )) $value = $value . 'T00:00:00.000Z';
        if (isDate($value) && ($operator === '<' || $operator === '<=' )) $value = $value . 'T23:59:59.999Z';
        if ($value === NULL && gettype($value) === 'NULL') $value = 'NULL';
        return $value;
    }

    /**
     * @return mixed
     */
    public function getQuery(): string
    {
        return $this->generateQuery();
    }

    /**
     * @param false $onlyRecords
     * @return QueryResult|bool|array|Collection
     */
    public function runQuery(bool $onlyRecords = false): QueryResult|bool|array|Collection
    {
        return (new SfClient())->query($this->generateQuery(),$onlyRecords);
    }

    /**
     * @param false $onlyRecords
     * @return bool|Collection
     */
    public function runQueryMore(bool $onlyRecords = false): bool|Collection
    {
        return (new SfClient())->queryMore($this->generateQuery(),$onlyRecords);
    }

}
