<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

class PayPalService {
    private $client;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/paypal.php';
        $this->initializeClient();
    }

    private function initializeClient() {
        $credentials = $this->config['sandbox'] ? $this->config['credentials']['sandbox'] : $this->config['credentials']['production'];
        
        $environment = $this->config['sandbox'] 
            ? new SandboxEnvironment($credentials['client_id'], $credentials['client_secret'])
            : new ProductionEnvironment($credentials['client_id'], $credentials['client_secret']);
        
        $this->client = new PayPalHttpClient($environment);
    }

    public function createOrder($amount, $reservation_id) {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reservation_id,
                'description' => $this->config['payment_description'],
                'amount' => [
                    'currency_code' => $this->config['currency'],
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'return_url' => $this->config['return_url'],
                'cancel_url' => $this->config['cancel_url'],
                'brand_name' => 'Room Reservation System',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
            ]
        ];

        try {
            $response = $this->client->execute($request);
            return [
                'status' => 'success',
                'order_id' => $response->result->id,
                'approval_url' => $this->getApprovalUrl($response)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function captureOrder($order_id) {
        $request = new OrdersCaptureRequest($order_id);

        try {
            $response = $this->client->execute($request);
            
            return [
                'status' => 'success',
                'order_id' => $response->result->id,
                'payment_id' => $response->result->purchase_units[0]->payments->captures[0]->id,
                'status' => $response->result->status,
                'amount' => $response->result->purchase_units[0]->payments->captures[0]->amount->value,
                'currency' => $response->result->purchase_units[0]->payments->captures[0]->amount->currency_code,
                'payer' => [
                    'email' => $response->result->payer->email_address,
                    'payer_id' => $response->result->payer->payer_id,
                    'name' => $response->result->payer->name->given_name . ' ' . $response->result->payer->name->surname
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function getApprovalUrl($response) {
        foreach ($response->result->links as $link) {
            if ($link->rel === 'approve') {
                return $link->href;
            }
        }
        return null;
    }
} 