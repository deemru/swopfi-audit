<?php

require __DIR__ . '/common.php';

// globals
$SWOP_users = [];
$total_SWOP_amount = 0;
$total_SWOP_mass_amount = 0;
$last_interest = 0;

$dApp = '3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS';
dAppReproduce( $wk, [ getTxs( $wk, $dApp ) ], getFunctions() );

function SWOP_userInit( $address )
{
    global $SWOP_users;

    if( !isset( $SWOP_users[$address] ) )
    {
        $SWOP_users[$address]['SWOP_amount'] = 0;
        $SWOP_users[$address]['SWOP_claimed_amount'] = 0;
        $SWOP_users[$address]['SWOP_last_claimed_amount'] = 0;
        $SWOP_users[$address]['last_interest'] = 0;
    }
}

function getFunctions()
{
    global $dApp;

    return
    [
        16 => 
        [
            $dApp =>
            [
                'airDrop' => function( $tx )
                {
                    global $SWOP_users;
                    global $last_interest;
                    global $total_SWOP_amount;
                    global $total_SWOP_mass_amount;
            
                    $pmtAmount = $tx['payment'][0]['amount'];
            
                    $last_interest = $last_interest + fraction( $pmtAmount, 100000000, $total_SWOP_amount );
                    $total_SWOP_amount = $total_SWOP_amount;
                    $total_SWOP_mass_amount = $total_SWOP_mass_amount + $pmtAmount;
                },

                'lockSWOP' => function( $tx )
                {
                    global $SWOP_users;
                    global $last_interest;
                    global $total_SWOP_amount;
                    global $total_SWOP_mass_amount;
            
                    $sender = $tx['sender'];
                    SWOP_userInit( $sender );

                    $pmtAmount = $tx['payment'][0]['amount'];
                    $claimAmount = fraction( $SWOP_users[$sender]['SWOP_amount'], $last_interest - $SWOP_users[$sender]['last_interest'], 100000000 );

                    $total_SWOP_amount = $total_SWOP_amount + $pmtAmount + $claimAmount;
                    $total_SWOP_mass_amount = $total_SWOP_mass_amount + $pmtAmount;

                    $SWOP_users[$sender]['SWOP_amount'] = $SWOP_users[$sender]['SWOP_amount'] + $pmtAmount + $claimAmount;
                    $SWOP_users[$sender]['SWOP_claimed_amount'] = $SWOP_users[$sender]['SWOP_claimed_amount'] + $claimAmount;
                    $SWOP_users[$sender]['SWOP_last_claimed_amount'] = $claimAmount;
                    $SWOP_users[$sender]['last_interest'] = $last_interest;
                },
                
                'withdrawSWOP' => function( $tx )
                {
                    global $SWOP_users;
                    global $last_interest;
                    global $total_SWOP_amount;
                    global $total_SWOP_mass_amount;
            
                    $sender = $tx['sender'];
                    SWOP_userInit( $sender );

                    $withdrawAmount = $tx['call']['args'][0]['value'];

                    $claimAmount = fraction( $SWOP_users[$sender]['SWOP_amount'], $last_interest - $SWOP_users[$sender]['last_interest'], 100000000 );

                    $total_SWOP_amount = $total_SWOP_amount - $withdrawAmount + $claimAmount;
                    $total_SWOP_mass_amount = $total_SWOP_mass_amount - $withdrawAmount;

                    $diff = $SWOP_users[$sender]['SWOP_amount'] - $withdrawAmount + $claimAmount;
                    if( $diff < 0 )
                    {
                        $diff = -$diff;

                        $total_SWOP_amount += $diff;
                        $total_SWOP_mass_amount += $diff;

                        $diff = 0;
                    }

                    $SWOP_users[$sender]['SWOP_amount'] = $diff;
                    $SWOP_users[$sender]['SWOP_claimed_amount'] = $SWOP_users[$sender]['SWOP_claimed_amount'] + $claimAmount;
                    $SWOP_users[$sender]['SWOP_last_claimed_amount'] = $claimAmount;
                    $SWOP_users[$sender]['last_interest'] = $last_interest;
                },

                'claimAndStakeSWOP' => function( $tx )
                {
                    global $SWOP_users;
                    global $last_interest;
                    global $total_SWOP_amount;
                    global $total_SWOP_mass_amount;
            
                    $sender = $tx['sender'];
                    SWOP_userInit( $sender );

                    $claimAmount = fraction( $SWOP_users[$sender]['SWOP_amount'], $last_interest - $SWOP_users[$sender]['last_interest'], 100000000 );

                    $total_SWOP_amount = $total_SWOP_amount + $claimAmount;
                    $total_SWOP_mass_amount = $total_SWOP_mass_amount;

                    $SWOP_users[$sender]['SWOP_amount'] = $SWOP_users[$sender]['SWOP_amount'] + $claimAmount;
                    $SWOP_users[$sender]['SWOP_claimed_amount'] = $SWOP_users[$sender]['SWOP_claimed_amount'] + $claimAmount;
                    $SWOP_users[$sender]['SWOP_last_claimed_amount'] = $claimAmount;
                    $SWOP_users[$sender]['last_interest'] = $last_interest;
                },

                'claimAndWithdrawSWOP' => function( $tx )
                {
                    global $SWOP_users;
                    global $last_interest;
                    global $total_SWOP_amount;
                    global $total_SWOP_mass_amount;
            
                    $sender = $tx['sender'];
                    SWOP_userInit( $sender );

                    $pmtAmount = 0;
                    $claimAmount = fraction( $SWOP_users[$sender]['SWOP_amount'], $last_interest - $SWOP_users[$sender]['last_interest'], 100000000 );

                    $total_SWOP_amount = $total_SWOP_amount;
                    $total_SWOP_mass_amount = $total_SWOP_mass_amount - $claimAmount;

                    $SWOP_users[$sender]['SWOP_amount'] = $SWOP_users[$sender]['SWOP_amount'];
                    $SWOP_users[$sender]['SWOP_claimed_amount'] = $SWOP_users[$sender]['SWOP_claimed_amount'] + $claimAmount;
                    $SWOP_users[$sender]['SWOP_last_claimed_amount'] = $claimAmount;
                    $SWOP_users[$sender]['last_interest'] = $last_interest;
                },

                'shutdown' => function( $tx ){},
                'activate' => function( $tx ){},
                'updateWeights' => function( $tx ){},
            ],
        ],
    ];
}

if( 0 )
{
    $json = [];
    $json[] = [ 'key' => 'total_SWOP_amount', 'type' => 'integer', 'value' => $total_SWOP_amount ];
    $json[] = [ 'key' => 'total_SWOP_mass_amount', 'type' => 'integer', 'value' => $total_SWOP_mass_amount ];
    $json[] = [ 'key' => 'last_interest', 'type' => 'integer', 'value' => $last_interest ];

    foreach( $SWOP_users as $address => $vals )
    {
        $json[] = [ 'key' => $address . '_SWOP_amount', 'type' => 'integer', 'value' => $vals['SWOP_amount'] ];
        $json[] = [ 'key' => $address . '_SWOP_claimed_amount', 'type' => 'integer', 'value' => $vals['SWOP_claimed_amount'] ];
        $json[] = [ 'key' => $address . '_SWOP_last_claimed_amount', 'type' => 'integer', 'value' => $vals['SWOP_last_claimed_amount'] ];
        $json[] = [ 'key' => $address . '_last_interest', 'type' => 'integer', 'value' => $vals['last_interest'] ];
    }

    file_put_contents( 'swopfi_governance_final_v2.json', json_encode( $json, JSON_PRETTY_PRINT ) );

    $csv = file_get_contents( 'result_ultimate_final.csv' );
    $csv = explode( "\n", $csv );
    foreach( $csv as $line )
    {
        if( empty( $line ) )
            continue;
        $ex = explode( ',', $line );
        if( intval( $ex[1] ) !== $SWOP_users[$ex[0]]['SWOP_amount'] )
            $wk->log( 'e', 'fail' );
    }
}

if( 0 )
{
    if( 0 )
    {
        $json = file_get_contents( 'swopfi_governance.json' );
        $json = $wk->json_decode( $json );

        $SWOP_users = [];

        $total_SWOP_amount = 0;
        $last_interest = 0;

        foreach( $json as $ktv )
        {
            $key = $ktv['key'];
            $type = $ktv['type'];
            $value = $ktv['value'];

            if( $key === 'total_SWOP_amount' )
            {
                $total_SWOP_amount = $value;
                continue;
            }

            if( $key === 'last_interest' )
            {
                $last_interest = $value;
                continue;
            }

            if( substr( $key, 36 ) === 'SWOP_amount' )
            {
                $SWOP_users[substr( $key, 0, 35 )]['SWOP_amount'] = $value;
                continue;
            }

            if( substr( $key, 36 ) === 'SWOP_claimed_amount' )
            {
                $SWOP_users[substr( $key, 0, 35 )]['SWOP_claimed_amount'] = $value;
                continue;
            }

            if( substr( $key, 36 ) === 'SWOP_last_claimed_amount' )
            {
                $SWOP_users[substr( $key, 0, 35 )]['SWOP_last_claimed_amount'] = $value;
                continue;
            }

            if( substr( $key, 36 ) === 'last_interest' )
            {
                $SWOP_users[substr( $key, 0, 35 )]['last_interest'] = $value;
                continue;
            }
        }
    }
}

function calculateTotal()
{
    global $wk;
    global $SWOP_users;
    global $last_interest;
    global $total_SWOP_amount;
    global $total_SWOP_mass_amount;

    $total_SWOP_amount_calculated = 0;

    foreach( $SWOP_users as $address => $user )
    {
        if( !isset( $user['SWOP_amount'] ) || $user['SWOP_amount'] < 0 )
        {
            continue;
        }

        $reward = fraction( $user['SWOP_amount'], $last_interest - $user['last_interest'], 100000000 );
        $total_SWOP_amount_calculated += $user['SWOP_amount'] + $reward;
    }

    //if( abs( (int)$total_SWOP_mass_amount - (int)$total_SWOP_amount_calculated ) > 1 )
        //$total_SWOP_amount = $total_SWOP_amount;

    $wk->log( amount( (int)$total_SWOP_amount, 8 ) );
    $wk->log( amount( (int)$total_SWOP_mass_amount, 8 ) );
    $wk->log( amount( (int)$total_SWOP_amount_calculated, 8 ) ); // 40359.44109974

    $wk->log( amount( (int)$total_SWOP_mass_amount - (int)$total_SWOP_amount_calculated, 8 ) ); // 40359.44109974
}    

calculateTotal();
