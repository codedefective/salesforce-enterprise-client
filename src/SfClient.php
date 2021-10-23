<?php

namespace Codedefective\SalesforceEnterpriseClient;

ini_set("soap.wsdl_cache_enabled", 0);
ini_set('soap.wsdl_cache',0);
use SforceEnterpriseClient;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use QueryOptions;
use QueryResult;

class SfClient
{
    protected string $wsdl;
    private int $maxSize = 1000;
    protected string $logDirectory;
    private array $connectionData = [];
    public SforceEnterpriseClient $client;

    public function __construct()
    {
        $this->logDirectory = config('salesforce_enterprise.log_directory');
        $this->wsdl = $this->getWsdlPath();
        $this->connect();
    }


    /**
     * @return void
     */
    private function connect(): void
    {
        try {
            $this->client  = new SforceEnterpriseClient();
            $this->client->createConnection($this->wsdl);
            $conn_file_name = $this->getLogPath();

            if(Storage::exists($conn_file_name)){
                $content = trim(Storage::get($conn_file_name));
                $this->connectionData = $content == "" ? []: explode("@@", $content);
            }

            if (count($this->connectionData) < 2) {
                $this->client->login
                (
                    username: config("salesforce_enterprise.current.username"),
                    password: config('salesforce_enterprise.current.password').config('salesforce_enterprise.current.token')
                );

                Storage::put($conn_file_name, $this->client->getLocation()."@@".$this->client->getSessionId().'@@current');
                $text_message = "New Connection";
            }else{
                $this->client->setEndpoint($this->connectionData[0]);
                $this->client->setSessionHeader($this->connectionData[1]);
                $text_message = "Current Session";
            }

            Log::info("Sf Login Success: ". $text_message);

        }catch (Exception $e){
            Log::error("Sf Login Error: ". $e->getMessage());
        }
    }

    /**
     * @param string $query
     * @param bool $onlyRecords
     * @return Collection|QueryResult|array|bool
     */
    public function query(string $query, bool $onlyRecords=false): Collection | QueryResult | array | bool
    {
        $response = $this->client->query($query);
        return $onlyRecords ? ( $response->records ? collect( $response->records): false) : $response;
    }

    /**
     * @param $query
     * @param bool $onlyRecords
     * @return Collection|bool
     */
    public function queryMore($query, bool $onlyRecords=false) : Collection | bool
    {
        $this->client->setQueryOptions(new QueryOptions($this->maxSize));
        $responseList = $this->client->query($query);
        $complete= false;
        $queryMoreRecords = new Collection();
        if ($responseList->size > 0){
            while (!$complete){
                $queryMoreRecords = $queryMoreRecords->merge($responseList->records);
                if($responseList->done != true) {
                    $responseList = $this->client->queryMore($responseList->queryLocator);
                }else{
                    $complete = true;
                }
            }
        }

        return empty($queryMoreRecords) ? false : ($onlyRecords ? collect($queryMoreRecords) : collect(['size' => $responseList->size,'records' => $queryMoreRecords]));
    }

    /**
     * @return string
     */
    private  function getWsdlPath(): string
    {
        return Storage::build([
            'driver' => 'local',
            'root' => base_path()
        ])->path(config('salesforce_enterprise.wsdl_path'));
    }

    /**
     * @return string
     */
    private function getLogPath() :string
    {
        return $this->logDirectory . '/' . date('Y') . '/' . date('m') . '/' . date('d'). '/' . 'sf_connection_'.date('Y_m_d').'.txt';
    }
}
