<?php

namespace Opencart\Catalog\Controller\Extension\BunceApi\Module;

class BunceApi extends \Opencart\System\Engine\Controller
{
    public function triggerCheckoutAccessBeforeEvent($route, &$args): void
    {
        // Code to execute before the checkout process starts
        // For example, checking or interacting with Bunce API before order confirmation

        // Load the Bunce API settings
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('bunce_api');

        $api_key = $settings['bunce_api_key'] ?? '';
        $event_id = $settings['bunce_api_event_id'] ?? '';

        if ($api_key && $event_id) {
            // Prepare the payload to send to the Bunce API
            $payload = [
                'event' => 'checkout_access_before',
                'event_id' => $event_id,
                'customer_id' => $this->customer->getId(),
                'cart_total' => $this->cart->getTotal(),
                'session_id' => session_id(),
            ];

            // Send data to the Bunce API (Example using curl)
            $this->sendToBunceApi($payload, $api_key);
        }
    }

    public function triggerCheckoutAccessAfterEvent($route, &$args, &$output): void
    {
        // Code to execute after the checkout process is complete
        // For example, sending order data to the Bunce API after confirmation

        // Load the Bunce API settings
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('bunce_api');

        $api_key = $settings['bunce_api_key'] ?? '';
        $event_id = $settings['bunce_api_event_id'] ?? '';

        if ($api_key && $event_id && isset($args[0]['order_id'])) {
            // Load the order information
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($args[0]['order_id']);

            if ($order_info) {
                // Prepare the payload to send to the Bunce API
                $payload = [
                    'event' => 'checkout_access_after',
                    'event_id' => $event_id,
                    'order_id' => $order_info['order_id'],
                    'customer_id' => $order_info['customer_id'],
                    'order_total' => $order_info['total'],
                    'currency' => $order_info['currency_code'],
                ];

                // Send data to the Bunce API (Example using curl)
                $this->sendToBunceApi($payload, $api_key);
            }
        }
    }

    private function sendToBunceApi(array $payload, string $api_key): void
    {
        $url = 'https://api.bunce.com/event'; // Example API endpoint

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            $this->log->write('Bunce API request failed: ' . $error);

            // Optionally save to the database for debugging
            $this->db->query("INSERT INTO `" . DB_PREFIX . "bunce_api_log` SET `event` = '" . $this->db->escape(json_encode($payload)) . "', `response` = '" . $this->db->escape($error) . "', `created_at` = NOW()");
        } else {
            $this->log->write('Bunce API response: ' . $response);

            // Optionally save to the database for future reference
            $this->db->query("INSERT INTO `" . DB_PREFIX . "bunce_api_log` SET `event` = '" . $this->db->escape(json_encode($payload)) . "', `response` = '" . $this->db->escape($response) . "', `created_at` = NOW()");
        }

        curl_close($ch);
    }
}
