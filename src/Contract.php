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
     * Default gas
     * @var int
     */
    private $defaultGas = 50000;

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
     * get token name
     *
     * @return string|null
     * @throws Exception
     */
    public function getName() : ?string
    {
        $name = null;
        $this->contract->call('name', function($err, $res) use (&$name) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $name = end($res);
            }
        });

        return $name;
    }

    /**
     * get token symbol
     *
     * @return string|null
     * @throws Exception
     */
    public function getSymbol() : ?string
    {
        $symbol = null;
        $this->contract->call('symbol', function($err, $res) use (&$symbol) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $symbol = end($res);
            }
        });

        return $symbol;
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
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        $result = null;
        $this->contract->call($name, $args, function($err, $res) use (&$result) {
            if ($err) {
                throw new \Exception($err->getMessage(), $err->getCode());
            } else {
                $result = $res;
            }
        });

        return $result;
    }
}