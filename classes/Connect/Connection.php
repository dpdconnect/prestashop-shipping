<?php

namespace DpdConnect\classes\Connect;

use Configuration;
use DpdConnect\classes\Version;
use DpdConnect\Sdk\ClientBuilder;
use DpdConnect\Sdk\Common\HttpClient;
use DpdConnect\Sdk\Exceptions\AuthenticateException;
use DpdConnect\Sdk\Exceptions\HttpException;
use DpdConnect\Sdk\Objects\MetaData;
use DpdConnect\Sdk\Objects\ObjectFactory;
use DpdConnect\classes\DpdEncryptionManager;
use DpdConnect\Sdk\Resources\Token;
use Exception;

class Connection
{
    protected $url;
    protected $username;
    protected $password;
    protected $client;

    public function __construct()
    {
        $this->url = Configuration::get('dpdconnect_url');
        $this->username = Configuration::get('dpdconnect_username');
        $encryptedPassword = Configuration::get('dpdconnect_password');
        if (!$encryptedPassword) {
            throw new Exception('No credentials provided');
        }

        $this->password = DpdEncryptionManager::decrypt($encryptedPassword);
        $clientBuilder = new ClientBuilder($this->url, ObjectFactory::create(MetaData::class, [
            'webshopType' => Version::type(),
            'webshopVersion' => Version::webshop(),
            'pluginVersion' => Version::plugin(),
        ]));
        $this->client = $clientBuilder->buildAuthenticatedByPassword($this->username, $this->password);
        $this->client->setCacheCallable(new \DpdConnect\classes\Connect\DpdConnectCache());

        $this->client->getAuthentication()->setJwtToken(
            Configuration::get('dpdconnect_jwt_token') ?: null
        );

        $this->client->getAuthentication()->setTokenUpdateCallback(function ($jwtToken) {
            Configuration::updateValue('dpdconnect_jwt_token', $jwtToken);
            $this->client->getAuthentication()->setJwtToken($jwtToken);
        });
    }

    /**
     * @throws AuthenticateException
     * @throws HttpException
     */
    public function getPublicJwtToken()
    {
        $token = new Token(
            new HttpClient($this->url)
        );

        return $token->getPublicJWTToken($this->username, $this->password);
    }
}
