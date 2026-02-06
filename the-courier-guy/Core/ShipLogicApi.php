<?php

if (file_exists(plugin_dir_path(__DIR__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__DIR__) . 'vendor/autoload.php';
}
require_once plugin_dir_path(__DIR__) . 'Core/ShipLogicContentPayload.php';
require_once plugin_dir_path(__DIR__) . 'Core/ShipLogicApiPayload.php';

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class ShipLogicApi
{
    public const LEGACY_API_BASE              = 'https://api.shiplogic.com/';
    public const API_BASE                     = 'https://api.shiplogic.com/v2/';
    const        TCG_SHIP_LOGIC_GETRATES_BODY = 'tcg_ship_logic_getrates_body';
    private string $access_key_id;
    private string $secret_access_key;
    private string $accessBearerToken;
    private        $apiMethods = [
        'getRates'         => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'rates',
        ],
        'getOptInRates'    => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'rates/opt-in',
        ],
        'createShipment'   => [
            'method'   => 'POST',
            'endPoint' => self::API_BASE . 'shipments',
        ],
        'getShipments'     => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments?tracking_ref=',
        ],
        'trackShipment'    => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments?tracking_reference=',
        ],
        'getShipmentLabel' => [
            'method'   => 'GET',
            'endPoint' => self::API_BASE . 'shipments/label?id=',
        ],
        'getLockerLocations'         => [
                'method'   => 'GET',
                'endPoint' => self::API_BASE . 'pickup-points?order_closest=true&search=',
            ],
    ];

    private $sender;
    private $receiver;
    private $logging;
    private $log;


    public function __construct(string $access_key_id, string $secret_access_key, string $accessBearerToken, $logging)
    {
        $this->access_key_id     = $access_key_id;
        $this->secret_access_key = $secret_access_key;
        $this->accessBearerToken = $accessBearerToken;
        $this->logging           = $logging;
        $this->log               = wc_get_logger();
    }

    /**
     * @param string $apiMethod
     * @param array $data
     *
     * @return string
     * @throws GuzzleException
     */
    public function makeAPIRequest(string $apiMethod, array $data): string
    {
        $client  = new Client();
        $amzDate = date('Ymd\THis\Z');
        $headers = [
            'X-Amz-Date'   => $amzDate,
            'Cookie'       => 'XDEBUG_SESSION=PHPSTORM',
            'Content-Type' => 'application/json',
        ];

        $isLegacy = true;

        if (!empty($this->accessBearerToken)) {
            $headers["Authorization"] = 'Bearer ' . $this->accessBearerToken;
            $isLegacy                 = false;
        }

        $method = $this->getApiMethod($apiMethod, "method", $isLegacy);
        $uri    = $this->getApiMethod($apiMethod, "endPoint", $isLegacy);

        $request = null;

        if ($method === 'POST') {
            $request = new Request(
                $method,
                $uri,
                $headers,
                $data['body']
            );
        } elseif ($method === 'GET') {
            $uri     .= $data['param'];
            $request = new Request(
                $method,
                $uri,
                $headers
            );
        }

        if ($request === null) {
            return "Invalid request...";
        }

        if (empty($this->accessBearerToken)) {
            $request = $this->signRequest($request);
        }

        $response = $client->send($request);

        return $response->getBody()->getContents();
    }

    /**
     * @param array $package
     * @param array $parameters
     *
     * @return array
     * @throws GuzzleException|ShipLogicApiException
     */
    public function getOptInRates(array $package, array $parameters): array
    {
        $this->sender             = $this->getSender($parameters);
        $this->receiver           = $this->getReceiver($package);
        $body                     = new stdClass();
        $body->collection_address = $this->sender;
        $body->delivery_address   = $this->receiver;
        $hash                     = 'tcg_optin_' . hash('sha256', serialize($body));
        $optInRates               = get_transient($hash);
        if ($optInRates) {
            return $optInRates;
        }

        try{
            $optInRates = $this->makeAPIRequest(
                'getOptInRates',
                ['body' => json_encode($body)]
            );

            $optInRates = json_decode($optInRates, true);
        } catch (Exception $exception){
            $optInRates = [];
        }

        if (!empty($optInRates)) {
            set_transient($hash, $optInRates, 300);
        }

        return $optInRates;
    }

    /**
     * @param array $package
     * @param array $parameters
     *
     * @return array
     * @throws GuzzleException|ShipLogicApiException
     */
    public function getRates(array $package, array $parameters): array
    {
        if ($wcSession = WC()->session) {
            $wcSession->set(self::TCG_SHIP_LOGIC_GETRATES_BODY, null);

            $optInRates = $this->getOptInRates($package, $parameters);

            $body                     = new stdClass();
            $body->collection_address = $this->sender;
            $body->delivery_address   = $this->receiver;

            $payloadApi   = new ShipLogicApiPayload();
            $parcelsArray = $payloadApi->getContentsPayload($parameters, $package['contents']);
            $parcels      = [];

            unset($parcelsArray['fitsFlyer']);

            foreach ($parcelsArray as $parcelArray) {
                $parcel                        = new stdClass();
                $parcel->submitted_length_cm   = $parcelArray['dim1'];
                $parcel->submitted_width_cm    = $parcelArray['dim2'];
                $parcel->submitted_height_cm   = $parcelArray['dim3'];
                $parcel->submitted_description = $this->removeTrailingComma($parcelArray['description']);
                $parcel->item_count            = $parcelArray['itemCount'] ?? $parcelArray['item'];
                $parcel->submitted_weight_kg   = wc_get_weight($parcelArray['actmass'], 'kg');
                $parcels[]                     = $parcel;
            }

            $body->parcels = $parcels;

            $body->account_id     = (int)$parameters['account'];
            $body->declared_value = 0.00;
            $insurance            = false;
            if (!empty($package['insurance']) && $package['insurance']) {
                $insurance = true;
            }
            if (!empty($package['cart_subtotal']) && $insurance) {
                $body->declared_value = (float)$package['cart_subtotal'];
            } elseif (!empty($package['contents_cost']) && $insurance) {
                $body->declared_value = (float)$package['contents_cost'];
            }

            if (!empty($package['ship_logic_optins'])) {
                $body->opt_in_rates = $package['ship_logic_optins'];
            }

            if (!empty($package['ship_logic_time_based_optins'])) {
                $body->opt_in_time_based_rates = $package['ship_logic_time_based_optins'];
            }

            $hash  = 'tcg_rates_' . hash('sha256', serialize($body));
            $rates = get_transient($hash);
            $wcSession->set(self::TCG_SHIP_LOGIC_GETRATES_BODY, $body);

            /**
             * Log Request & response
             */
            $this->logging ? $this->log->add('thecourierguy', 'Calculate_shipping request: ' . json_encode($body)) : '';
            $this->logging ? $this->log->add(
                'thecourierguy',
                'Calculate_shipping response: ' . json_encode($rates)
            ) : '';

            if ($rates) {
                return ['rates' => $rates, 'opt_in_rates' => $optInRates];
            }

            try{
                $response = $this->makeAPIRequest('getRates', ['body' => json_encode($body)]);
                $rates    = json_decode($response, true);

                $enabledTcgLockers = $parameters['enable_tcg_lockers'] ?? '0';

                if ($enabledTcgLockers) {
                    $lockerRates = $this->getLockerRates($body);

                    if (!empty($lockerRates)) {
                        $rates['rates'] = array_merge($rates['rates'], $lockerRates);
                    }
                }

                if (!empty($rates['rates'])) {
                    set_transient($hash, $rates, 300);
                }
            } catch (Exception $exception){
                wc_clear_notices();
                wc_add_notice($exception->getMessage(), 'error');
                $rates = [];
            }

            return ['rates' => $rates, 'opt_in_rates' => $optInRates];
        }

        return [];
    }

    public function getLockerRates($body): ?array
    {
        $lockerLocationsResponse = $this->makeAPIRequest('getLockerLocations', [
            'param' => $body->delivery_address->city
        ]);

        if (empty($lockerLocationsResponse)) {
            return null;
        }

        $lockerLocations = json_decode($lockerLocationsResponse, true);

        if (empty($lockerLocations['pickup_points']) || count($lockerLocations['pickup_points']) <= 0) {
            return null;
        }

        $pickupPoint = $lockerLocations['pickup_points'][0];

        unset($body->delivery_address);
        $body->delivery_pickup_point_id = $pickupPoint['pickup_point_id'];
        $body->delivery_pickup_point_provider = $pickupPoint['pickup_point_provider'];

        $hash  = 'tcg_locker_rates_' . hash('sha256', serialize($body));
        $rates = get_transient($hash);

        if ($rates) {
            return $rates;
        }

        $response = $this->makeAPIRequest('getRates', ['body' => json_encode($body)]);

        $rates = json_decode($response, true);

        if (!empty($rates['rates'])) {
            foreach ($rates['rates'] as &$rate) {
                if (isset($rate['service_level']['description'])) {
                    $originalDescription = $rate['service_level']['description'];

                    $rate['service_level']['description'] = "{$pickupPoint['address']['company']}";
                    $rate['service_level']['description'] .= " - {$pickupPoint['address']['entered_address']}.";
                    $rate['service_level']['description'] .= " {$pickupPoint['trading_hours']}.";
                    $rate['service_level']['description'] .= " {$originalDescription}";
                    $rate['service_level']['code'] .= "/{$pickupPoint['pickup_point_id']}-{$pickupPoint['address']['company']}";
                    $rate['service_level']['pickup_point'] = "{$pickupPoint['pickup_point_id']}";
                }
            }

             set_transient($hash, $rates, 300);
        }

        return $rates['rates'] ?? [];
    }

    public function removeTrailingComma($string)
    {
        $lastOccurrence = strrpos($string, ', ');

        if ($lastOccurrence !== false) {
            return substr($string, 0, $lastOccurrence);
        } else {
            return $string;
        }
    }

    /**
     * @param object $body
     *
     * @return string
     * @throws GuzzleException
     */
    public function createShipment(object $body): string
    {
        return $this->makeAPIRequest(
            'createShipment',
            ['body' => json_encode($body)]
        );
    }

    /**
     * @throws GuzzleException
     */
    public function getShipmentLabel($id): string
    {
        return $this->makeAPIRequest('getShipmentLabel', ['param' => $id]);
    }

    /**
     * @param array $parameters
     *
     * @return object
     * @throws ShipLogicApiException
     */
    private function getSender(array $parameters): object
    {
        $states = WC()->countries->get_states($parameters['shopCountry']);

        $sender                 = new stdClass();
        $sender->contact_name   = $parameters['shopContactName'] ?? '';
        $sender->company        = $parameters['company_name'] ?? '';
        $sender->street_address = $parameters['shopAddress1'] . ", " . $parameters['shopSuburb'];
        $sender->local_area     = $parameters['shopAddress2'] ?? '';
        $sender->city           = $parameters['shopCity'];
        $sender->zone           = $states[$parameters['shopState']];
        $sender->country        = $parameters['shopCountry'];
        $sender->code           = $parameters['shopPostalCode'];

        return $sender;
    }

    /**
     * @param array $package
     *
     * @return object
     * @throws ShipLogicApiException
     */
    private function getReceiver(array $package): object
    {
        $states          = WC()->countries->get_states($package['destination']['country']);
        $destination     = $package['destination'];

        $receiver                 = new stdClass();
        $receiver->company        = $package['billing_company'] ?? '';
        $receiver->street_address = $destination['address'];
        $receiver->local_area     = $destination['address_2'];
        $receiver->city           = $destination['city'];
        $receiver->zone           = $states[$destination['state']];
        $receiver->country        = $destination['country'];
        $receiver->code           = $destination['postcode'];

        return $receiver;
    }

    private function signRequest(RequestInterface $request): RequestInterface
    {
        $signature   = new SignatureV4('execute-api', 'af-south-1');
        $credentials = new Credentials($this->access_key_id, $this->secret_access_key);

        return $signature->signRequest($request, $credentials);
    }

    private function getApiMethod($method, $type, $isLegacy): string
    {
        $apiBase = $isLegacy ? self::LEGACY_API_BASE : self::API_BASE;

        $apiMethods = [
            'getRates'         => [
                'method'   => 'POST',
                'endPoint' => $apiBase . 'rates',
            ],
            'getOptInRates'    => [
                'method'   => 'POST',
                'endPoint' => $apiBase . 'rates/opt-in',
            ],
            'createShipment'   => [
                'method'   => 'POST',
                'endPoint' => $apiBase . 'shipments',
            ],
            'getShipments'     => [
                'method'   => 'GET',
                'endPoint' => $apiBase . 'shipments?tracking_ref=',
            ],
            'trackShipment'    => [
                'method'   => 'GET',
                'endPoint' => $apiBase . 'shipments?tracking_reference=',
            ],
            'getShipmentLabel' => [
                'method'   => 'GET',
                'endPoint' => $apiBase . 'shipments/label?id=',
            ],
            'getLockerLocations'         => [
                'method'   => 'GET',
                'endPoint' => $apiBase . 'pickup-points?order_closest=true&search=',
            ],
        ];

        return $apiMethods[$method][$type];
    }
}

class ShipLogicApiException extends Exception
{

}
