<?php
/**
 * api.php - Claude API Integration
 * 
 * This file handles the communication between the frontend chatbot
 * and the Claude API. It receives user messages, forwards them to Claude,
 * and returns Claude's responses back to the frontend.
 */

// Set headers to return JSON and handle CORS if needed
header('Content-Type: application/json');
// Uncomment the line below if you need CORS support
// header('Access-Control-Allow-Origin: *');

// Load configuration
require_once 'config.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'error' => 'Method not allowed, only POST requests are accepted'
    ]);
    exit;
}

// Get and validate the request body
$input = json_decode(file_get_contents('php://input'), true);

// Log incoming requests (optional, comment out in production)
error_log('Received request: ' . json_encode($input));

// Validate required fields
if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'error' => 'No message provided or message is empty'
    ]);
    exit;
}

// Extract data from the request
$message = trim($input['message']);
$conversation_history = isset($input['history']) ? $input['history'] : [];

// Optional: Validate and sanitize the conversation history
if (!empty($conversation_history)) {
    // Ensure the history is an array
    if (!is_array($conversation_history)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Conversation history must be an array'
        ]);
        exit;
    }
    
    // Validate each entry in the history
    foreach ($conversation_history as $entry) {
        if (!isset($entry['role']) || !isset($entry['content'])) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid conversation history format'
            ]);
            exit;
        }
        
        // Validate roles (must be 'user' or 'assistant')
        if (!in_array($entry['role'], ['user', 'assistant'])) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid role in conversation history'
            ]);
            exit;
        }
    }
}

// Structure the messages array for Claude API
$messages = array_merge(
    $conversation_history,
    [['role' => 'user', 'content' => $message]]
);

// Prepare the request data for Claude API
$request_data = [
    'model' => $config['claude_model'],
    'max_tokens' => $config['max_tokens'],
    'temperature' => $config['temperature'],
    'messages' => $messages
];

// Optional: Add system prompt if configured
if (isset($config['system_prompt']) && !empty($config['system_prompt'])) {
    $request_data['system'] = $config['system_prompt'];
}

// Initialize cURL session for the API request
$ch = curl_init('https://api.anthropic.com/v1/messages');

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request_data),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $config['claude_api_key'],
        'anthropic-version: 2023-06-01'  // Update this if needed based on Claude's API version
    ],
    CURLOPT_TIMEOUT => 30,  // 30-second timeout
    CURLOPT_SSL_VERIFYPEER => true  // Verify SSL certificate (important for security)
]);

// Execute the cURL request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

// Handle cURL errors
if ($curl_error) {
    error_log('cURL Error: ' . $curl_error);
    http_response_code(500);
    echo json_encode([
        'error' => 'Error connecting to Claude API',
        'details' => $curl_error
    ]);
    exit;
}

// Handle HTTP errors from Claude's API
if ($http_code !== 200) {
    error_log('Claude API Error: ' . $response);
    http_response_code(502); // Bad Gateway
    echo json_encode([
        'error' => 'Error from Claude API',
        'status_code' => $http_code,
        'details' => json_decode($response, true)
    ]);
    exit;
}

// Decode Claude's response
$claude_response = json_decode($response, true);

// Validate the response structure
if (!isset($claude_response['content']) || !isset($claude_response['content'][0]['text'])) {
    error_log('Unexpected response structure from Claude API: ' . $response);
    http_response_code(502);
    echo json_encode([
        'error' => 'Unexpected response structure from Claude API'
    ]);
    exit;
}

// Extract the response text
$response_text = $claude_response['content'][0]['text'];

// Log the complete response for debugging (optional, comment out in production)
error_log('Claude Response: ' . json_encode($claude_response));

// Return the successful response
echo json_encode([
    'message' => $response_text,
    'model' => $claude_response['model'],
    'id' => $claude_response['id'],
    'type' => 'message',  // Include message type for the frontend
    'timestamp' => time() // Add timestamp for the frontend
]);
?>