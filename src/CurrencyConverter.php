<?php

namespace Beycan\MultiChain;

final class CurrencyConverter
{
    /**
     * @var string
     */
    private $api;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string|null
     */
    private $apiKey = null;

    /**
     * @var array
     */
    private $apis = [
        'cryptocompare' => 'https://min-api.cryptocompare.com/data/price',
        'coinmarketcap' => 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion'
    ];

    /**
     * @var array
     */
    private $stableCoins = [
        'USDT',
        'USDC',
        'DAI',
        'BUSD',
        'UST',
        'TUSD'
    ];

    /**
     * @param string $api
     * @param string|null $apiKey
     * @throws Exception
     */
    public function __construct(string $api, ?string $apiKey = null)
    {
        if (!isset($this->apis[$api])) {
            throw new \Exception('Unsupported api!');
        }

        $this->api = $api;
        $this->apiKey = $apiKey;
        $this->apiUrl = $this->apis[$api];
    }

    /**
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return float|null
     * @throws Exception
     */
    public function convert(string $from, string $to, float $amount) : ?float
    {
        if ($this->api == 'coinmarketcap') {
            return $this->convertWithCoinMarketCap($from, $to, $amount);
        } elseif ($this->api == 'cryptocompare') {
            return $this->convertWithCryptoCompare($from, $to, $amount);
        } else {
            return null;
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return float|null
     * @throws Exception
     */
    public function convertWithCoinMarketCap(string $from, string $to, float $amount) : ?float
    {
        $this->checkUsedApi('coinmarketcap');
        
        if ($this->isStableCoin($from, $to)) {
            return floatval($amount);
        }

        $parameters = [
            'amount' => $amount,
            'symbol' => $from,
            'convert' => $to
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: ' . $this->apiKey
        ];

        $qs = http_build_query($parameters); 
        $request = "{$this->apiUrl}?{$qs}";


        $curl = curl_init($request);

        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1 
        ]);

        $response = json_decode(curl_exec($curl));

        if (isset($response->data)) {
            $result = $this->toFixed($response->data->quote->{strtoupper($to)}->price);
        } else {
            $result = null;
        }

        curl_close($curl); 

        return $result;
    }

    /**
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return float|null
     * @throws Exception
     */
    public function convertWithCryptoCompare(string $from, string $to, float $amount) : ?float
    {
        $this->checkUsedApi('cryptocompare');

        if ($this->isStableCoin($from, $to)) {
            return floatval($amount);
        }

        $apiUrl =  $this->apiUrl . '?fsym=' . $from . '&tsyms=' . $to;
        $convertData = json_decode(file_get_contents($apiUrl));
        if (isset($convertData->$to)) {
            $price = $amount * $convertData->$to;
            return $this->toFixed($price);
        } else {
            return null;
        }
    }

    /**
     * @param string $api
     * @return void
     * @throws Exception
     */
    private function checkUsedApi(string $api) : void
    {
        if ($this->api != $api) {
            throw new \Exception(sprintf('The api chosen to be used is not the "%s" api!', $api));
        } else {
            if ($this->apiKey == null && $this->api == 'coinmarketcap') {
                throw new \Exception("The key of the api selected to be used has not been entered.");
            }
        }
    }

    /**
     * @param float $number
     * @param int $decimals
     * @return float
     */
    public function toFixed(float $number, int $decimals = 6) : float
    {
        return floatval(number_format($number, $decimals, '.', ""));
    }

    /**
     * @param $exp
     * @return void
     */
    public function expToStr($exp) : string
    {
        $parsedNumber = explode("e", strtolower($exp));

        if (count($parsedNumber) == 1) {
            return $exp;
        }

        list($mantissa, $exponent) = $parsedNumber;
        list($int, $dec) = explode(".", $mantissa);

        bcscale(abs($exponent-strlen($dec)));
        $result = bcmul($mantissa, bcpow("10", $exponent));

        if ($result > 1) {
            $result = rtrim($result, '.0');
        }

        return $result;
    }

    /**
     * @param string $from
     * @param string $to
     * @return boolean
     */
    private function isStableCoin(string $from, string $to) : bool
    {
        if (strtoupper($from) == 'USD' || strtoupper($to) == 'USD') {
            if (in_array(strtoupper($from), $this->stableCoins) || in_array(strtoupper($to), $this->stableCoins)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $symbols
     * @return void
     */
    private function addStableCoins(array $symbols) : void
    {
        $this->stableCoins = array_merge($this->stableCoins, $symbols);
    }
}
