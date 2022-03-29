<?php

namespace Beycan\MultiChain;

use Web3\Contract as Web3Contract;
use Web3\Validators\AddressValidator;

final class Contract
{
    /**
     * Provider
     * @var Provider
     */
    private $provider;

    /**
     * Current token contract address
     * @var string
     */
    private $address;

    /**
     * web3 contract
     * @var Web3Contract
     */
    public $contract;

    /**
     * @param string $address
     * @param array $abi
     */
    public function __construct(string $address, array $abi)
    {
        if (AddressValidator::validate($address) === false) {
            throw new \Exception('Invalid contract address!', 23000);
        }

        $this->address = $address;
        $this->provider = MultiChain::getProvider();
        $this->contract = (new Web3Contract($this->provider::getHttpProvider(), json_encode($abi)))->at($address);
    }

    /**
     * @param string $method
     * @param array $params
     * @return string|null
     * @throws Exception
     */
    public function getEstimateGas(string $method, ...$params) : ?string
    {
        $result = null;
        call_user_func_array([$this->contract, 'estimateGas'], [$method, ...$params, function($err, $res) use (&$result) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $result = $res;
            }
        }]);


        return $result;
    }

    /**
     * Returns the current token contract address
     * @return string
     */
    public function getAddress() : string
    {
        return $this->address;
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function __call(string $method, array $params)
    {
        $result = null;
        call_user_func_array([$this->contract, 'call'], [$method, ...$params, function($err, $res) use (&$result) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $result = $res;
            }
        }]);

        return $result;
    }
}