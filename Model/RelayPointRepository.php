<?php
/**
 * See LICENSE.md for license details.
 */

namespace GlsGroup\Shipping\Model;

use GlsGroup\Shipping\Api\RelayPointRepositoryInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;

class RelayPointRepository implements RelayPointRepositoryInterface
{

    const CLIENT_ID = '9ljNqKSpWDWVyObRmwdPCzGHNN1ZDM3g';
    const CLIENT_SECRET = 'YGw33hGoVXJArOyo';

    const BASE_URL = 'https://api.gls-group.net';


    private $scopeConfig;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $curlClient;

    public function __construct(Curl $curl, ScopeConfigInterface $scopeConfig)
    {
        $this->curlClient = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    private function authenticate()
    {
        $url = self::BASE_URL . '/oauth2/v2/token';

        $this->curlClient->setHeaders(['Content-Type' => 'application/x-www-form-urlencoded']);

        $this->curlClient->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $res = json_decode($this->curlClient->getBody(), true);
        return $res['access_token'];
    }

    public function getList($countryId, $q)
    {
        $token = $this->authenticate();

        $data = [
            'address' => $q,
            'countryCode' => $countryId,
            'availableFrom'=> $this->getWorkingDay(1),
            'availableTo'=> $this->getWorkingDay(13),
            'minAvailableRate' => 0.75,
            'limit' => '10'
        ];


        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
        $url = self::BASE_URL . '/parcel-shop-management/v2/available-out-of-home-locations-by-address?' . http_build_query($data);


        $this->curlClient->setHeaders($headers);

        $this->curlClient->get($url);
        $response = json_decode($this->curlClient->getBody(), true);

        if (empty($response['data'])) {
            return [];
        }
        $relayPointList = [];

        foreach ($response['data'] as $relayPoint) {
            $relayPointList[] = [
                'id' => $relayPoint['parcelShopId'],
                'name' => $relayPoint['name'],
                'type' => $relayPoint['type'],
                'distance' => $this->formatDistance(($relayPoint['distance'])),
                'address' => $relayPoint['address']['street'] . ' ' . $relayPoint['address']['houseNumber'],
                'city' => $relayPoint['address']['city'],
                'zipcode' => $relayPoint['address']['zipCode'],
                'additionalInfo' => $relayPoint['address']['additionalInfo'] ?? '',
                'schedule' => $this->groupSimilarOpeningHours($relayPoint['openingDays']),
            ];
        }

        return $relayPointList;
    }


    private function formatDistance($distance)
    {
        if ($distance < 1) {
            return round($distance * 1000) . ' m';
        }
        return round($distance, 1) . ' km';
    }

    private function groupSimilarOpeningHours($openingHours)
    {
        $groupedOpeningHours = [];

        foreach ($openingHours as $openingHour) {
            if (empty($groupedOpeningHours)) {
                $groupedOpeningHours[] = ["title" => $openingHour["weekday"], "from" => $openingHour["weekday"], "to" => $openingHour["weekday"], "hours" => $this->stringifyOpeningHour($openingHour["hours"])];
            }

            $lastGroup = array_pop($groupedOpeningHours);
            if ($lastGroup["hours"] === $this->stringifyOpeningHour($openingHour["hours"])) {
                $lastGroup["to"] = $openingHour["weekday"];
                $lastGroup["title"] = __($lastGroup["from"]) . '.-' . __($openingHour["weekday"]);

                array_push($groupedOpeningHours, $lastGroup);
            } else {
                array_push($groupedOpeningHours, $lastGroup);
                $groupedOpeningHours[] = ["title" => $openingHour["weekday"], "from" => $openingHour["weekday"], "to" => $openingHour["weekday"], "hours" => $this->stringifyOpeningHour($openingHour["hours"])];
            }


        }

        return $groupedOpeningHours;
    }

    private function stringifyOpeningHour($hours)
    {
        if (empty($hours)) {
            return '';
        }

        return $hours[0]["openingTime"] . '-' . $hours[0]["closingTime"];
    }

    private function getWorkingDay($days) {
        $date = new \DateTime();
        $dayCount = 0;

        while ($dayCount < $days) {
            $date->modify('+1 day');
            // Check if it's a weekend
            if ($date->format('N') < 6) {
                $dayCount++;
            }
        }

        return $date->format('Y-m-d');
    }
}
