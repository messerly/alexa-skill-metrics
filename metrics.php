<?php
### Source/Author: https://github.com/messerly/alexa-skill-metrics
### Created: 2/12/2021
class Stats extends CI_Controller {
    // This function uses CodeIgniter, put can be tweaked to be used elsewhere
    // This function will provide AWS Alexa Skill: Flash Briefings metrics
    public function index()
    {
        // Set variables for reseting the accesss token
        // TO DO: Insert refresh token, client id, and client secret
        $refresh_token = "";
        $client_id = "";
        $client_secret = "";
        $token_url = "https://api.amazon.com/auth/o2/token";

        // Set parameters for access token query
        $request_data = array(
        "client_id" => $client_id,
        "client_secret" => $client_secret,
        "refresh_token" => $refresh_token,
        "grant_type" => "refresh_token"
        );

        // Initialize curl with base URL
        $ch = curl_init($token_url);

        // Return the transfer as a string of the curl_exec() value
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Set method to POST and specify the parameter fields
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_data));

        // Set HTTP header from Amazon's documentation
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));

        $access_result = curl_exec($ch);

        // Close the curl resource
        curl_close($ch);

        // Translate JSON code for accessToken variable
        $json = json_decode($access_result);

        if (isset($json->refresh_token))
        {
            global $refreshToken;
            $refreshToken = $json->refresh_token;
        }

        $accessToken = $json->access_token;
        $token_type = $json->token_type;

        // Query database to get organization rows
        // TO DO: Put your model and info for the rows you need
        $query = $this->model->org_all_rows();

        $b = 0;
        $c = 1;
        $d = 2;

        foreach ($query as $row)
        {
            // Grab organization info from database
            // TO DO: Change "org_id" to the field in your database (and "skill_id" if it's different)
            $org_id = $row->org_id;
            $skill_id = $row->skill_id;

            // Set variables for Metrics API curl query
            $metric = ["totalSessions", "uniqueCustomers", "totalEnablements"];
            $metric_variables = ["sessions", "unique_customers", "enablements"];

            // Only pull metrics for organizations that have a skill id
            if(!is_null($skill_id))
            {
                for($i = 0; $i < count($metric); $i++)
                {
                    // Set timezone and dates
                    date_default_timezone_set("UTC");
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $today = date('Y-m-d');
                    $start_time = $yesterday . "T05:00:00Z";
                    $end_time = $today . "T04:59:59Z";

                    // Set rest of the paramenters for query
                    $period = "SINGLE";
                    $stage = "live";
                    $skill_type = "flashBriefing";
                    $stats_url = "/v1/skills/$skill_id/metrics?startTime=$start_time&endTime=$end_time&period=$period&metric=$metric[$i]&stage=$stage&skillType=$skill_type&locale=en-US";

                    // Initialize curl
                    $ch = curl_init();

                    // Authorization header
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                      'Authorization: ' . $accessToken,
                      'Accept: application/json',
                      'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
                    ));

                    // Set query data with the URL
                    curl_setopt($ch, CURLOPT_URL, 'https://api.amazonalexa.com' . $stats_url);

                    // Tell it to return data
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $output[] = curl_exec($ch);

                    // Check for a 200 reply
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    // Close the curl resource and free system resources
                    curl_close($ch);

                }
                for($a = 0; $a < count($output); $a++)
                {
                    // Store only value of query by stripping out everything we don't need
                    $first = implode('', array_slice(explode('[', $output[$a]), 2, 1));
                    $metric_variable[$a] = implode('', array_slice(explode(']', $first), 0, 1));
                }

                $sessions = $metric_variable[$b];
                $unique_customers = $metric_variable[$c];
                $enablements = $metric_variable[$d];

                // TO DO: Change model and query name
                $this->model->add_stat($org_id, $yesterday, $sessions, $unique_customers, $enablements);

                $b = $b+3;
                $c = $c+3;
                $d = $d+3;
            }
        }
    }
}
?>
