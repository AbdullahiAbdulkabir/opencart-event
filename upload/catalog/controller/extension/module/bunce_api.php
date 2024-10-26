<?php

class ControllerExtensionModuleBunceApi extends Controller
{
    // Constants for Event Types
    const CHECKOUT_ACCESS_AFTER_EVENT = 'Checkout page accessed - after';
    const PRODUCT_VIEWED_EVENT = 'Product viewed';
    const PRODUCT_ADDED_TO_CART_EVENT = 'Product added to cart';
    const ABANDONED_CART_EVENT = 'Abandoned cart';

    // Method to check if the extension is active
    private function isExtensionActive()
    {
        return $this->config->get('bunce_api_status'); // Assuming this is the key for extension status
    }

    // Method to trigger event after checkout access
    public function triggerCheckoutAccessAfterEvent()
    {
        if ($this->isExtensionActive()) {

            $payload = $this->getUserData();

            $this->sendEvent(self::CHECKOUT_ACCESS_AFTER_EVENT, 'bunce_api_event_id', $payload);
        } else {
            $this->log->write('Bunce API: Extension is not active. Event not triggered.');
        }
    }

    // Method to trigger event when a product is viewed
    public function triggerViewProductEvent($route, $data)
    {
        if ($this->isExtensionActive()) {
            $this->log->write('Product viewed event triggered');
            $product_id = $this->getProductIdFromRequest($data);

            if ($product_id) {
                $payload = $this->getUserData($product_id);
                $this->sendEvent(self::PRODUCT_VIEWED_EVENT, 'bunce_api_event_id_2', $payload);
            } else {
                $this->log->write('No product_id found in the request data.');
            }
        } else {
            $this->log->write('Bunce API: Extension is not active. Event not triggered.');
        }
    }

    // Method to trigger event when a product is added to the cart
    public function triggerProductAddedToCartEvent($route, $data)
    {
        if ($this->isExtensionActive()) {
            if (isset($_POST['product_id'])) {
                $product_id = $_POST['product_id'];
                $payload = $this->getUserData($product_id);
                $this->sendEvent(self::PRODUCT_ADDED_TO_CART_EVENT, 'bunce_api_event_id_3', $payload);
            } else {
                $this->log->write('No product_id found in the POST data.');
            }
        } else {
            $this->log->write('Bunce API: Extension is not active. Event not triggered.');
        }
    }

    // Method to check and trigger abandoned cart events
    public function triggerAbandonedCartEvent()
    {
        if ($this->isExtensionActive()) {
            $abandoned_time = time() - ($this->config->get('bunce_api_abandoned_cart_duration') * 60);
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart` WHERE `date_added` < '" . date('Y-m-d H:i:s', $abandoned_time) . "'");

            foreach ($query->rows as $cart) {
                $payload = $this->prepareAbandonedCartPayload($cart);
                $this->sendEvent(self::ABANDONED_CART_EVENT, 'bunce_api_event_id_4', $payload);
            }
        } else {
            $this->log->write('Bunce API: Extension is not active. Abandoned cart check skipped.');
        }
    }

    // Private method to prepare payload for abandoned cart event
    private function prepareAbandonedCartPayload($cart)
    {
        $payload = [
            'cart_id' => $cart['cart_id'],
            'product_id' => $cart['product_id'],
            'quantity' => $cart['quantity'],
            'customer_id' => $cart['customer_id'] ?? null,
            'session_id' => $cart['session_id'] ?? null,
        ];

        if (!empty($cart['customer_id'])) {
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($cart['customer_id']);
            $payload['email'] = $customer_info['email'];
            $payload['customer_name'] = $customer_info['firstname'] . ' ' . $customer_info['lastname'];
        } else {
            $anonymous_data = $this->generateAnonymousUserData();
            $payload['email'] = $anonymous_data['email'];
            $payload['customer_name'] = $anonymous_data['customer_name'];
        }

        return $payload;
    }

    // Private method to get product ID from the request
    private function getProductIdFromRequest($data)
    {
        return $_GET['product_id'] ?? ($data[0]['product_id'] ?? null);
    }

    // Private method to gather user data for payload
    private function getUserData($product_id = null)
    {
        // Initialize payload with product_id if available
        $payload = $product_id ? ['product_id' => $product_id] : [];

        // Populate payload based on user login status
        if ($this->customer->isLogged()) {
            $payload += [
                'email' => $this->customer->getEmail(),
                'customer_name' => $this->customer->getFirstName() . ' ' . $this->customer->getLastName()
            ];
        } else {
            $anonymous_data = $this->generateAnonymousUserData();
            $payload += [
                'email' => $anonymous_data['email'],
                'customer_name' => $anonymous_data['customer_name']
            ];
        }

        return $payload;
    }


    // Private method to generate random user data
    private function generateAnonymousUserData()
    {
        return [
            'email' => 'user_' . uniqid() . '@example.com',
            'customer_name' => 'Guest_' . uniqid(),
        ];
    }


    // Private method to send event data with error handling
    private function sendEvent($message, $event_id_key, $additional_payload = [])
    {
        $event_id = $this->config->get($event_id_key);
        $api_key = $this->config->get('bunce_api_key');

        if ($event_id && $api_key) {
            $url = 'https://test.api.bunce.so/v1/events/trigger';
            $payload = array_merge(['message' => $message], $additional_payload);

            $data = [
                'event_id' => $event_id,
                'payload' => $payload
            ];

            try {
                $options = [
                    'http' => [
                        'header' => [
                            'X-Authorization: ' . $api_key,
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen(json_encode($data))
                        ],
                        'method' => 'POST',
                        'content' => json_encode($data),
                    ],
                ];

                $context = stream_context_create($options);
                $response = file_get_contents($url, false, $context);

                if ($response === FALSE) {
                    throw new Exception('Request failed.');
                } else {
                    $this->log->write('Bunce API Response: ' . $response);
                }
            } catch (Exception $e) {
                // Log any exceptions without displaying them
                $this->log->write('Bunce API Error: ' . $e->getMessage());
            }
        } else {
            $this->log->write('Bunce API Error: Missing event_id or api_key');
        }
    }
}
