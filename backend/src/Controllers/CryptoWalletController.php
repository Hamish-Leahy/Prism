<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\CryptoWalletService;

/**
 * HavenWallet Controller
 * Part of the Haven family of services
 * Secure multi-chain cryptocurrency wallet management
 */
class CryptoWalletController
{
    private CryptoWalletService $havenWallet;

    public function __construct(CryptoWalletService $havenWallet)
    {
        $this->havenWallet = $havenWallet;
    }

    public function createWallet(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $name = $data['name'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($name) || empty($password)) {
                throw new \InvalidArgumentException('Name and password are required');
            }

            $result = $this->havenWallet->createWallet($name, $password);
            
            // Add Haven branding to response
            $result['service'] = 'HavenWallet';
            $result['family'] = 'Haven';
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function importWallet(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $privateKey = $data['private_key'] ?? '';
            $name = $data['name'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($privateKey) || empty($name) || empty($password)) {
                throw new \InvalidArgumentException('Private key, name and password are required');
            }

            $result = $this->havenWallet->importWallet($privateKey, $name, $password);
            
            // Add Haven branding to response
            $result['service'] = 'HavenWallet';
            $result['family'] = 'Haven';
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getWallets(Request $request, Response $response): Response
    {
        try {
            $result = $this->havenWallet->getWallets();
            
            // Add Haven branding to response
            $result['service'] = 'HavenWallet';
            $result['family'] = 'Haven';
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getBalance(Request $request, Response $response): Response
    {
        try {
            $walletId = $request->getAttribute('walletId');
            $chain = $request->getQueryParams()['chain'] ?? null;

            if (empty($walletId)) {
                throw new \InvalidArgumentException('Wallet ID is required');
            }

            $result = $this->havenWallet->getWalletBalance($walletId, $chain);
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function sendTransaction(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $walletId = $data['wallet_id'] ?? '';
            $password = $data['password'] ?? '';
            $to = $data['to'] ?? '';
            $amount = $data['amount'] ?? 0;
            $chain = $data['chain'] ?? null;

            if (empty($walletId) || empty($password) || empty($to) || $amount <= 0) {
                throw new \InvalidArgumentException('All transaction parameters are required');
            }

            $result = $this->havenWallet->sendTransaction($walletId, $password, $to, $amount, $chain);
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getSupportedChains(Request $request, Response $response): Response
    {
        try {
            $result = $this->havenWallet->getSupportedChains();
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getTransactions(Request $request, Response $response): Response
    {
        try {
            $walletId = $request->getAttribute('walletId');

            if (empty($walletId)) {
                throw new \InvalidArgumentException('Wallet ID is required');
            }

            $result = $this->havenWallet->getWalletTransactions($walletId);
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
