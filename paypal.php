<?php
// PayPal Configuration
return [
    'sandbox' => true, // Set to false for production
    'credentials' => [
        'sandbox' => [
            'client_id' => 'AfnAsy5IAZUZ6wSw8e4-zWmZ3yuVVcL3-TvY3hZrT51Lpg99mSoIogJzyE4jWc-u2tXvBrXjbxvU1xt9',
            'client_secret' => 'AfnAsy5IAZUZ6wSw8e4-zWmZ3yuVVcL3-TvY3hZrT51Lpg99mSoIogJzyE4jWc-u2tXvBrXjbxvU1xt9',
        ],
        'production' => [
            'client_id' => '', // Add production credentials when going live
            'client_secret' => '',
        ]
    ],
    'currency' => 'PHP',
    'payment_description' => 'Room Reservation Payment',
    'return_url' => 'http://localhost/Tariza/user/payment-success.php',
    'cancel_url' => 'http://localhost/Tariza/user/payment-cancel.php'
]; 