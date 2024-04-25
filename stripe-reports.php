<?php

// Include the Stripe PHP library
require_once './stripe-php/init.php';

// Load configuration (api key)
$ini_array = parse_ini_file('stripe-reports.ini');

// Set the API key
$stripe = new \Stripe\StripeClient($ini_array['stripe_api_key']);

// Create a report run
$report_request = $stripe->reporting->reportRuns->create([
    'report_type' => 'balance_change_from_activity.itemized.3',
    'parameters' => [
        'interval_start' => strtotime('first day of January ' . date('Y')),
        'interval_end' => strtotime('today'),
        'columns' => ['customer_id', 'customer_email', 'customer_name', 'customer_description'],
    ],
]);

// Store the report run ID
$report_run_id = $report_request['id'];

// Output the report run ID
echo "Report run ID: " . $report_run_id . "\n";

// Wait for the report run to complete
sleep(10);

// Allow up to 20 minutes for the report to complete
for ($i = 1; $i <= 60; $i++) {
    $report_run = $stripe->reporting->reportRuns->retrieve($report_run_id);
    echo "Report run status: " . $report_run['status'] . "\n";
    if ($report_run['status'] != 'succeeded') {
        sleep(20);
    } else {
        break;
    }
}

if ($report_run['status'] != 'succeeded') {
    echo "Report run did not complete successfully.\n";
    exit;
}

// Download the report with curl like curl {report_url} -u {stripe_api_key}:
echo "Downloading report...\n";
$report_url = $report_run['result']['url'];
$file_path = './' . date('Y') . '_report.csv'; // specify the file name and path where you want to save the file

// Check if the file already exists and delete it
if (file_exists($file_path)) {
    echo "Deleting existing file...\n";
    unlink($file_path);
}

// Open the file for writing
$file = fopen($file_path, 'w');

echo "Report URL: " . $report_url . "\n";

// Use curl to download the report
$ch = curl_init($report_url);

curl_setopt($ch, CURLOPT_USERPWD, $ini_array['stripe_api_key'] . ':'); // use stripe api key with basic auth
curl_setopt($ch, CURLOPT_FILE, $file); // write curl response to file

curl_exec($ch);

print curl_error($ch);

curl_close($ch);
