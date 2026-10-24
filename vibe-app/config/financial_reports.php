<?php

// Accounts the financial reports use when a cash fund receipt is posted: Dr cash, Cr the account for the receipt kind.
return [
    'cash_account' => ['code' => '1010202024', 'title' => 'Cash in Bank-Local Currency, Current Account-Land Bank of the Philippines ( LBP)'],
    'receipt_credit' => [
        'nca' => ['code' => '4030101000', 'title' => 'Subsidy from National Government'],
        'cash_advance' => ['code' => '1990101000', 'title' => 'Advances for Operating Expenses'],
    ],
];
