<?php

namespace ooglek\ShareASale;

use DOMDocument;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

/**
 * Class Client
 *
 * @package ooglek\ShareASale
 */
class Client
{
    /**
     * @var string
     */
    protected $serviceUrl;

    /**
     * @var string
     */
    protected $servicePath;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $merchantId;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * You can use the below protected properties to debug and fetch raw results from the API
     * If you want or need to
     */

    /**
     * @var GuzzleHttp\Psr7\Response
     */
    protected $httpResponse;

    /**
     * @var array
     */
    protected $query;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var string
     */
    protected $sig;

    protected $transactionActions = [
        'void',
        'edit',
        'find',
        'new',
        'reference',
    ];

    protected $reportActions = [
        'transactiondetail',
        'weeklyprogress',
        'affiliatetimespan',
        'activitysummary',
        'datafeeddownloads',
        'todayataglance',
        'staterevenue',
        'report-affiliate',
        'transactioneditreport',
        'transactionvoidreport',
        'apitokencount',
        'ledger',
        'affiliateTags',
        'balance',
    ];

    protected $maintenanceActions = [
        'bannerList',
        'bannerUpload',
        'bannerEdit',
        'dealList',
        'dealUpload',
        'dealEdit',
        'approveAffiliate',
        'declineAffiliate',
        'MassTagAffiliates',
    ];

    /**
     * @param string $merchantId
     * @param string $token
     * @param string $secretKey
     * @param string $version
     * @param string $serviceUrl
     * @param string $servicePath
     * @return object
     */
    public function __construct(string $merchantId=null, string $token=null, string $secretKey=null,
        string $version='3.0', string $serviceUrl='https://api.shareasale.com', string $servicePath='/w.cfm')
    {
        $this->merchantId = $merchantId;
        $this->token = $token;
        $this->secretKey = $secretKey;
        $this->version = $version;
        $this->serviceUrl = $serviceUrl;
        $this->servicePath = $servicePath;
    }

    /**
     * Automatically create get* and set* methods
     * Automatically create methods for each ShareASale action
     *
     *  $this->getSecretKey()
     *  $this->setSecretKey()
     *  $this->void(array $params)
     *  $this->activitysummary(array $params)
     *
     * @param   string  $func       Name of the function called
     * @param   array   $params     Array of Parameters passed
     * @return  mixed
     */
    public function __call($func, $params)
    {
        if (in_array(substr($func, 0, 3), ['get', 'set'])) {
            $verb = substr($func, 0, 3);
            if (property_exists(get_class($this), lcfirst(substr($func,3)))) {
                $prop = lcfirst(substr($func, 3));
                if ($verb === 'get') {
                    return $this->$prop;
                } elseif ($verb === 'set') {
                    if (count($params) === 1) {
                        $this->$prop = $params[0];
                        return;
                    } else {
                        throw new \ArgumentCountError("Method requires 1 parameter");
                    }
                }
            } else {
                throw new Exception("Property does not exist");
            }
        }
        $xml = '';
        foreach(['transactionActions', 'reportActions', 'maintenanceActions'] as $varname) {
            // Handle case mismatches but use the correct case for the actual SAS action
            if (($k = array_search(strtolower($func), array_map('strtolower', $this->$varname))) !== false) {
                $action = $this->$varname[$k];
                if (substr($varname, 0, 5) != 'trans') {
                    $xml = $action;
                }
                if (count($params) === 0) {
                    $params[0] = [];
                }
                return $this->callServiceMethod($action, $xml, $params[0]);
                break;
            }
        }
        throw new \BadMethodCallException("Method does not exist");
    }

    /**
     * @param int $merchantId
     * @return string
     * @throws Exception
     */
    public function getMerchantDescription(int $merchantId) : string
    {
        $ch = curl_init('https://account.shareasale.com/shareASale_b.cfm?merchantId=' . $merchantId . '&storeId=0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if (200 !== $info['http_code'])
        {
            throw new Exception('Error fetching merchant description: ' . var_export([$result, $info], true));
        }

        $return = '';
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(str_replace("\n", '', $result));
        foreach ($dom->getElementsByTagName('body') as $node)
        {
            $return .= $dom->saveHtml($node);
        }

        return $return;
    }

    /**
     * @param $serviceMethod
     * @param $xmlRecordTag
     * @param array $options
     * @return mixed            Array for XML, String for Non-XML
     * @throws GuzzleException
     */
    public function callServiceMethod($serviceMethod, $xmlRecordTag, $options = [])
    {
        $parameters = array_merge($this->getDefaultArguments(), ['action' => $serviceMethod]);
        if (!empty($xmlRecordTag)) {
            $parameters = array_merge($parameters, ['XMLFormat' => 1, 'format' => 'xml']);
        }
        $parameters = array_merge($parameters, $options);

        $this->httpResponse = $this->apiCall($serviceMethod, $parameters);
        if (!empty($xmlRecordTag)) {
            return $this->responseToRecords($this->httpResponse, $xmlRecordTag);
        }
        $body = $this->httpResponse->getBody();
        $body->seek(0);
        return trim($body->getContents());
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getDefaultArguments() : array
    {
        if (empty($this->version) || empty($this->merchantId) || empty($this->token))
        {
            throw new Exception('Required Parameter is not set');
        }

        return [
            'version'     => $this->version,
            'merchantID' => $this->merchantId,
            'token'       => $this->token,
        ];

    }

    /**
     * @param Response $httpResponse
     * @param $xmlRecordTag
     * @return array|array[]|\array[][]
     */
    protected function responseToRecords(Response $httpResponse, $xmlRecordTag) : array
    {
        // parse response body
        $body = $httpResponse->getBody();
        $body->seek(0); // ensure it is rewound
        $xml = simplexml_load_string($body->getContents());
        if (false === $xml)
        {
            foreach (libxml_get_errors() as $error)
            {
                echo "\t", $error->message . PHP_EOL;
            }
        }
        $records = [];
        if ($xml instanceof SimpleXMLElement)
        {
            // convert xml to an array
            $arr = @json_decode(@json_encode($xml), true);
            if (isset($arr[$xmlRecordTag]))
            {
                $records = $arr[$xmlRecordTag];
            }
            // normalize the result array if we have a single result
            if (is_array($arr) && !array_key_exists(0, $records))
            {
                $records = [$arr];
            }

            if (!is_array($records))
            {
                $records = [$records];
            }

            if (1 === count($records))
            {
                $records = reset($records);
            }
        }
        else
        {
            echo "FALSE ELSE" . PHP_EOL;
            foreach (libxml_get_errors() as $error)
            {
                echo "\t", $error->message . PHP_EOL;
            }
        }

        return $records;
    }

    /**
     * @param string $actionVerb
     * @param array $query
     * @return Response|ResponseInterface
     * @throws GuzzleException
     * @throws Exception
     */
    public function apiCall(string $actionVerb, array $query = [])
    {
        if (empty($this->token) || empty($this->secretKey) || empty($this->serviceUrl) || empty($this->servicePath))
        {
            throw new Exception('Required Parameter is not set');
        }

        try
        {
            $client = new GuzzleClient([
                'base_uri' => $this->getServiceUrl(),
            ]);

            $myTimeStamp = gmdate(DATE_RFC1123);
            $this->sig = $this->token . ':' . $myTimeStamp . ':' . $actionVerb . ':' . $this->secretKey;
            $sigHash = hash("sha256", $this->sig);

            $this->query = $query;

            $this->headers = [
                'x-ShareASale-Date'           => $myTimeStamp,
                'x-ShareASale-Authentication' => $sigHash,
            ];

            $httpResponse = $client->request('GET', $this->servicePath, [
                    'query'   => $this->query,
                    'headers' => $this->headers
            ]);

            if (!$httpResponse instanceof Response)
            {
                throw new Exception('Expected instance of GuzzleHttp\Psr7\Response not received');
            }

            if (!$httpResponse->getBody() instanceof Stream)
            {
                throw new Exception('Expected instance of GuzzleHttp\Psr7\Stream not received');
            }

            if ($httpResponse->getReasonPhrase() != 'OK')
            {
                throw new Exception('Expected response not received. Response details: ' . $httpResponse->getBody()->getContents());
            }

            $body = trim($httpResponse->getBody()->getContents());
            if (!empty($query['format']) && $query['format'] == 'xml' && substr($body, 0, 1) != '<') {
                throw new \Exception($body);
            } elseif (stripos($body, 'Invalid Request') !== false) {
                throw new \Exception($body);
            }

            return $httpResponse;

        }
        catch (RequestException $zhce)
        {
            $message = 'Error in request to Web service: ' . $zhce->getMessage();
            throw new Exception($message, $zhce->getCode());
        }

    }
}

