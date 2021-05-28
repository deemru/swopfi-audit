<?php

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, dApp()->address() ) ], getFunctions() );

class dApp_Early_Birds
{
    function address()
    {
        return '3PJuspTjxHhEJQjMEmLM5upiGQYCtCi5LyD';
    }

    function __construct( $dApp )
    {
        $this->keyActivateHeight = "activate_height";
        $this->keyFinishHeight = "finish_height";
        $this->totalShareSWOP = 100000000000000; # 1m with 8 digits
        $this->SWOP = 'Ehie5xYpeN8op1Cctc6aGUrqx8jq3jtf1DSjXDbfm7aT';
        $this->keyUserSWOPClaimedAmount = "_SWOP_claimed_amount";
        $this->keyUserSWOPLastClaimedAmount = "_SWOP_last_claimed_amount";

        $this->$dApp = $dApp;
    }

    function activateHeight()
    {
        return $this->getInteger( $this->keyActivateHeight );
    }

    function finishHeight()
    {
        return $this->getInteger( $this->keyFinishHeight );
    }

    function getInteger( $key, $default = null )
    {
        if( !isset( $this->db[$key] ) && isset( $default ) )
            return $default;
        return $this->db[$key];
    }

    function IntegerEntry( $key, $value )
    {
        $this->db[$key] = $value;
    }

    function ScriptTransfer( $address, $amount, $asset )
    {
        $this->transfers[$address][$asset] = $amount + ( isset( $this->transfers[$address][$asset] ) ? $this->transfers[$address][$asset] : 0 );
        $this->transfers[$asset][$address] = $amount + ( isset( $this->transfers[$asset][$address] ) ? $this->transfers[$asset][$address] : 0 );
    }

    function getCallerShare( $caller )
    {
        return $this->getInteger( "share_" . $caller );
    }

    function getClaimedAmount( $caller )
    {
        return $this->getInteger( $caller . $this->keyUserSWOPClaimedAmount, 0 );
    }

    function claimSWOP( $tx )
    {
        global $height;

        $blockDuration = $this->finishHeight() - $this->activateHeight();
        $currentDuration  = $height < $this->finishHeight() ? $height : $this->finishHeight();
        $userShare = $this->getCallerShare( $tx['sender'] );
        $userClaimedAmount = $this->getClaimedAmount( $tx['sender'] );
        $claimAmount = fraction( $currentDuration - $this->activateHeight(), $userShare, $blockDuration ) - $userClaimedAmount;
        $userClaimedAmountNew = $userClaimedAmount + $claimAmount;
        {
            $this->ScriptTransfer( $tx['sender'], $claimAmount, $this->SWOP );
            $this->IntegerEntry( $tx['sender'] . $this->keyUserSWOPClaimedAmount, $userClaimedAmountNew );
            $this->IntegerEntry( $tx['sender'] . $this->keyUserSWOPLastClaimedAmount, $claimAmount );
        }
    }

    function data( $tx )
    {
        foreach( $tx['data'] as $ktv )
        {
            $key = $ktv['key'];
            $value = $ktv['value'];

            if( !isset( $ktv['type'] ) && $value === null )
            {
                unset( $this->db[$key] );
                continue;
            }
            
            $this->db[$key] = $value;
        }
    }
}

function dApp()
{
    static $d;

    if( !isset( $d ) )
    {
        global $dApp;
        $d = new dApp_Early_Birds( $dApp );
    }

    return $d;
}

function getFunctions()
{
    return
    [
        16 => 
        [
            dApp()->address() =>
            [
                'claimSWOP' => function( $tx )
                {
                    dApp()->claimSWOP( $tx );
                },
            ],
        ],

        12 => 
        [
            dApp()->address() => function( $tx )
            {
                dApp()->data( $tx );
            },
        ],
    ];
}

$users_total_share = 0;
$users_total_claimed = 0;

$blockDuration = dApp()->finishHeight() - dApp()->activateHeight();
$currentDuration  = dApp()->finishHeight();
$lastDuraction = $currentDuration - dApp()->activateHeight();

foreach( dApp()->db as $key => $value )
{
    if( substr( $key, 0, 6 ) === 'share_' )
    {
        $users_total_share += $value;
        $user = substr( $key, 6 );

        $userShare = dApp()->getCallerShare( $user );
        $userClaimedAmount = dApp()->getClaimedAmount( $user );
        $claimAmount = fraction( $lastDuraction, $userShare, $blockDuration ) - $userClaimedAmount;

        $users_total_claimed += $userClaimedAmount + $claimAmount;
    }
}

$wk->log( 'users_total_share = ' . amount( $users_total_share, 8 ) );
$wk->log( 'users_total_claimed = ' . amount( $users_total_claimed, 8 ) );
$wk->log( 'diff = ' . amount( abs( $users_total_claimed - $users_total_claimed ), 8 ) );

$wk->log( 'done' );
