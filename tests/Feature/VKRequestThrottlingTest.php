<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiAuthException;
use VK\Exceptions\Api\VKApiFloodException;
use VK\Exceptions\Api\VKApiLimitsException;

class VKRequestThrottlingTest extends TestCase
{

    /** @var VKApiClient */
    private $vkApiClient;
    /** @var string */
    private $vkAccessToken;
    /** @var array */
    private $adParams;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->vkApiClient = new VKApiClient();
        $this->vkAccessToken = env('VK_FLOOD_CONTROL_TEST_ACCESS_TOKEN',Str::random(83));
        $this->adParams = [
            'ad_id' => 12345,
            'account_id' => 78901,
            'client_id' => 234567,
            'link_url' => 'http://vk.com/wall-1_1',
            'ad_platform' => 'all',
            'ad_format' => '9',
            'sex' => 2,
            'age_from' => 26,
            'age_to' => 30,
            'country' => '1',
            'cities' => '1,158,1074996,1102561,1113331',
            'cities_not' => '2,185,1000134,1035195',
            'groups' => '',
            'groups_not' => '',
            'interest_categories' => '',
            'retargeting_groups' => '',
            'retargeting_groups_not' => '',
            'groups_active' => ''
        ];
    }

    public function testComplicatedRequestThrottling()
    {
        $startTime = microtime(true);

        $sentRequests = 0;

        while ($sentRequests < 10) {

            try {
                $this->vkApiClient->ads()->getTargetingStats($this->vkAccessToken, $this->adParams);
            } catch (VKApiFloodException $floodException) {
                $this->fail('VkApiFloodException should not be thrown');
            } catch (VKApiAuthException $apiAuthException) {
                $this->assertFalse(env('DEFAULT_VK_ACCESS_TOKEN',false),'Incorrect access token');
            } catch (VKApiLimitsException $apiLimitsException) {
                $this->fail('Too many requests exception shouldn\'t be  thrown at all');
            }

            $this->assertFalse($sentRequests >= 5 && (microtime(true) - $startTime) < 10, "Should not be sent more than 5 requests 10 seconds");

            fwrite(STDERR, 'Complicated request #' . $sentRequests . ' ');

            $sentRequests++;

            fwrite(STDERR, 'Time: ' . (microtime(true) - $startTime) . PHP_EOL);

        }
    }

    public function testSimpleRequestThrottling() {

        $startTime = microtime(true);

        $sentRequests = 0;

        while ($sentRequests < 10) {

            try {
                $this->vkApiClient->ads()->getStatistics($this->vkAccessToken,$this->adParams);
            } catch (VKApiFloodException $floodException) {
                $this->fail('VkApiFloodException should not be thrown');
            } catch (VKApiAuthException $apiAuthException) {
                $this->assertFalse(env('DEFAULT_VK_ACCESS_TOKEN',false),'Incorrect access token');
            } catch (VKApiLimitsException $apiLimitsException) {
                $this->fail('Too many requests exception shouldn\'t be  thrown at all');
            }

            fwrite(STDERR, 'Simple request #' . $sentRequests . ' Time: ' . (microtime(true) - $startTime) . PHP_EOL);

            if ($sentRequests && !($sentRequests % 2)) {
                $this->assertTrue((microtime(true) - $startTime) >= 1, 'Should not be sent mor than 2 requests per 1 second');
                $startTime = microtime(true);
            }

            $sentRequests++;

        }
    }
}