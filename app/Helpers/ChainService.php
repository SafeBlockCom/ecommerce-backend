<?php
/**
 * Created by PhpStorm.
 * User: Sara
 * Date: 8/23/2024
 * Time: 10:23 PM
 */
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class ChainService
{

    public static function addTransaction($body)
    {
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json'
        ];

        try {
            $bRequest = new Request('POST', 'http://localhost:3000/addTransaction', $headers, json_encode($body));
            $res = $client->sendAsync($bRequest)->wait();
            $responseBody = $res->getBody()->getContents(); // Ensure the body content is returned correctly
            // Parse the JSON response into a PHP array
            $responseData = json_decode($responseBody, true); // The `true` argument makes it return an associative array
            return $responseData;
        } catch (RequestException $e) {
            // Log the error message for debugging
            AppException::log($e->getMessage());
            return null; // Return null or handle the error as needed
        }
    }
}
