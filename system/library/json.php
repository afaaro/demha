<?php
namespace System\Library;

class Json
{
    /**
     * Sends a successful JSON response and terminates the script.
     *
     * The response will include a "success" key set to true, along with the provided data.
     *
     * @param array|object $data The data to include in the response.
     */
    public static function success(array|object $data = [])
    {
        self::sendResponse(true, $data);
    }

    /**
     * Sends an error JSON response and terminates the script.
     *
     * The response will include a "success" key set to false and an error message.
     *
     * @param string $message The error message to send.
     */
    public static function error(string $message = 'An unknown error occurred.')
    {
        self::sendResponse(false, ['message' => $message]);
    }

    /**
     * Private method to handle the actual response sending logic.
     *
     * @param bool $success The success status of the response.
     * @param array $payload The data payload to be encoded in JSON.
     */
    private static function sendResponse(bool $success, array $payload)
    {
        // Set the Content-Type header to ensure the browser knows it's JSON
        header('Content-Type: application/json');

        // Create the final response array
        $response = [
            'success' => $success
        ] + $payload;

        // Encode the response to a JSON string and output it
        echo json_encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Terminate the script to prevent any further output
        exit;
    }
}