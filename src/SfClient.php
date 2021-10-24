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
    private string $sessionId;
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
        $this->client  = new SforceEnterpriseClient();
        try {
            $options = [
                'ssl_method' => SOAP_SSL_METHOD_TLS,
                'encoding'=>'UTF-8',
                'cache_wsdl'    => WSDL_CACHE_NONE,
                'stream_context'=> stream_context_create(
                    [
                        'ssl'=> [
                            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                            'ciphers' => 'SHA256',
                            'verify_peer'=>false,
                            'verify_peer_name'=>false,
                            'allow_self_signed' => true
                        ]
                    ]
                )
            ];
            $this->client->createConnection($this->wsdl,null,$options);
            $conn_file_name = $this->getLogPath();


            if(Storage::exists($conn_file_name) && $sessionId = trim(Storage::get($conn_file_name))){
                $this->sessionId = $sessionId;
            }else{
                $this->sessionId = $this->reGenerateSfSession();
            }

            $this->client->setEndpoint(config("salesforce_enterprise.location"));
            $this->client->setSessionHeader($this->sessionId);

            if ($this->client->getUserInfo()->userName <> config("salesforce_enterprise.username")) {
                $this->reGenerateSfSession();
            }

        }catch (Exception $e){
            Log::error("Sf Login Error [Regenerating]: ". $e->getMessage());
            $this->reGenerateSfSession();
        }
    }

    /**
     * @return string
     */
    private function reGenerateSfSession(): string
    {
        $this->client->login
        (
            username: config("salesforce_enterprise.username"),
            password: config('salesforce_enterprise.password').config('salesforce_enterprise.token')
        );

        $this->client->setEndpoint($this->client->getLocation());
        $sessionId = $this->client->getSessionId();
        $this->client->setSessionHeader($sessionId);
        Storage::put($this->getLogPath(), $sessionId);
        Log::info("[Regenerated] Sf Login Success: ". $sessionId);
        return $sessionId;
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
        return $this->logDirectory . '/.connection.ini';
    }
}
