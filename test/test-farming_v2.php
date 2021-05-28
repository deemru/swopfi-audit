<?php

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, dApp()->address() ), getTxs( $wk, governanceAddress()->address() ) ], getFunctions() );

function calcScaleValue( $pool )
{
    global $wk;
    static $db;
    $cachefile = '_cache_scaleValue_' . $pool . '.json';
    if( isset( $db[$cachefile] ) )
        return $db[$cachefile];
    if( file_exists( $cachefile ) )
    {
        $scaleValue = $wk->json_decode( file_get_contents( $cachefile ) );
    }
    else
    {
        $scaleValue = (int)substr( '10000000000000000', 0, getDecimals( $wk->getData( "share_asset_id", $pool ) ) + 1 );
        file_put_contents( $cachefile, json_encode( $scaleValue ) );
    }
    $db[$cachefile] = $scaleValue;
    return $scaleValue;
}

function calculateUserPoolTotal( $caller, $pool )
{
    [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ] = claimCalc( $pool, $caller );
    $userClaimedAmount = getUserSWOPClaimedAmount( $pool, $caller );
    return $userClaimedAmount + $claimAmount;
}

function getUserSWOPClaimedAmount( $pool, $user )
{
    global $dApp;
    global $keyUserSWOPClaimedAmount;

    return getInteger( $dApp, $pool . "_" . $user . $keyUserSWOPClaimedAmount, 0 );
}


function snapshot()
{
    $f = dApp();
    $g = governanceAddress();

    $postfix = "_share_tokens_locked";
    $targetlen = 35 + 1 + 35 + strlen( $postfix );

    $s = [];
    foreach( $f->db as $k => $v )
    {
        if( strlen( $k ) === $targetlen && strpos( $k, $postfix ) )
        {
            $pool = substr( $k, 0, 35 );
            $user = substr( $k, 36, 35 );
            $s[$pool][$user] = $v;
        }
    }

    static $lastHeight;
    static $lastUserTotals;
    global $height;

    foreach( $s as $pool => $users )
    {
        $rewardUpdateHeight = governanceAddress()->getInteger( "reward_update_height" );
        $totalRewardPerBlockCurrent = governanceAddress()->getInteger( "total_reward_per_block_current" );
        $totalRewardPerBlockPrevious = governanceAddress()->getInteger( "total_reward_per_block_previous" );
        $rewardPoolFractionCurrent = governanceAddress()->getInteger( $pool . "_current_pool_fraction_reward" );
        $rewardPoolFractionPrevious = governanceAddress()->getInteger( $pool . "_previous_pool_fraction_reward" );

        $rewardPoolCurrent = fraction( $totalRewardPerBlockCurrent, $rewardPoolFractionCurrent, 10000000000 );
        $rewardPoolPrevious = fraction( $totalRewardPerBlockPrevious, $rewardPoolFractionPrevious, 10000000000 );

        $userTotal = 0;
        foreach( $users as $user => $shares )
        {
            [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ] = dApp()->claimCalc( $pool, $user );
            $userClaimedAmount = dApp()->getInteger( $pool . "_" . $user . "_SWOP_claimed_amount", 0 );
            $userTotal += $userClaimedAmount + $claimAmount;
        }

        $userTotals[$pool] = $userTotal;

        if( isset( $lastHeight ) )
        {
            $poolReward = 0;
            if( $lastHeight < $rewardUpdateHeight )
                $poolReward += $rewardPoolPrevious * ( $rewardUpdateHeight - $lastHeight );
            if( $lastHeight > $rewardUpdateHeight )
                $rewardUpdateHeight = $lastHeight;
            $poolReward += $rewardPoolCurrent * ( $height - $rewardUpdateHeight );
            $poolTotals[$pool] = $poolReward;
        }
    }

    global $wk;
    static $spoolTotal;
    static $suserTotal;
    static $firstHeight;

    if( isset( $lastHeight ) )
    {
        if( !isset( $firstHeight ) )
            $firstHeight = $lastHeight;

        $poolTotal = 0;
        $userTotal = 0;
        foreach( $s as $pool => $users )
        {
            $poolFarming = $poolTotals[$pool];
            $poolTotal += $poolFarming;
            $userFarming = $userTotals[$pool] - ( isset( $lastUserTotals[$pool] ) ? $lastUserTotals[$pool] : 0 );
            $userTotal += $userFarming;

            $diff = $poolFarming - $userFarming;
            $wk->log( $pool . ': ' . amount( $poolFarming, 8 ) . ' === ' . amount( $userFarming, 8 ) . ' (' . amount( $diff, 8 ) . ')' );
        }

        $diff = $poolTotal - $userTotal;
        $wk->log( "SNAPSHOT ($lastHeight..$height): " . amount( $poolTotal, 8 ) . ' === ' . amount( $userTotal, 8 ) . ' (' . amount( $diff, 8 ) . ')' );

        $spoolTotal = $poolTotal + ( isset( $spoolTotal ) ? $spoolTotal : 0 );
        $suserTotal = $userTotal + ( isset( $suserTotal ) ? $suserTotal : 0 );
        $diff = $spoolTotal - $suserTotal;
        $wk->log( "TOTAL ($firstHeight..$height): " . amount( $spoolTotal, 8 ) . ' === ' . amount( $suserTotal, 8 ) . ' (' . amount( $diff, 8 ) . ')' );
    }

    $lastHeight = $height;
    $lastUserTotals = $userTotals;
}

class dApp_Governance
{
    function address()
    {
        return '3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS';
    }

    function __construct()
    {
        $this->pools = [];
        $this->updateWeightsCount = 0;
    }

    function getInteger( $key, $default = null )
    {
        if( !isset( $this->db[$key] ) && isset( $default ) )
            return $default;
        return $this->db[$key];
    }

    function invoke( $tx )
    {
        if( $tx['call']['function'] === 'updateWeights' )
        {
            //if( ++$this->updateWeightsCount === 2 )
                snapshot();
        }

        absorber( $this, $tx['stateChanges']['data'] );
    }

    function data( $tx )
    {
        absorber( $this, $tx['data'] );
    }
}

function governanceAddress()
{
    static $d;
    if( !isset( $d ) )
        $d = new dApp_Governance;
    return $d;
}

class dApp_Farming
{
    function getTotalShareTokenLocked( $pool )
    {
        return $this->getInteger( $pool . "_total_share_tokens_locked" );
    }
    function getLastInterestInfo( $pool )
    {
        global $height;
        $lastInterest = $this->getInteger( $pool . "_last_interest" );
        $lastInterestHeight = $this->getInteger( $pool . "_last_interest_height", $height );
        return [ $lastInterestHeight, $lastInterest ];
    }

    function rewardInfo( $pool )
    {
        $rewardUpdateHeight = governanceAddress()->getInteger( "reward_update_height" );
        $totalRewardPerBlockCurrent = governanceAddress()->getInteger( "total_reward_per_block_current" );
        $totalRewardPerBlockPrevious = governanceAddress()->getInteger( "total_reward_per_block_previous" );
        $rewardPoolFractionCurrent = governanceAddress()->getInteger( $pool . "_current_pool_fraction_reward" );
        $rewardPoolFractionPrevious = governanceAddress()->getInteger( $pool . "_previous_pool_fraction_reward" );

        $rewardPoolCurrent = fraction( $totalRewardPerBlockCurrent, $rewardPoolFractionCurrent, 10000000000 );
        $rewardPoolPrevious = fraction( $totalRewardPerBlockPrevious, $rewardPoolFractionPrevious, 10000000000 );

        return [ $rewardPoolCurrent, $rewardUpdateHeight, $rewardPoolPrevious ];
    }

    function getUserInterestInfo( $pool, $userAddress )
    {
        $lastInterest = $this->getInteger( $pool . "_last_interest" );
        $userLastInterest = $this->getInteger( $pool . "_" . $userAddress . "_last_interest", $lastInterest );
        $userShare = $this->getInteger( $pool . "_" . $userAddress . "_share_tokens_locked", 0 );

        return [ $userLastInterest, $userShare ];
    }

    function calcInterest( $lastInterestHeight, $rewardUpdateHeight, $lastInterest, $currentRewardPerBlock, $shareTokenLocked, $previousRewardPerBlock, $scaleValue )
    {
        if( $shareTokenLocked === 0 )
            return 0;

        global $height;

        if( $height < $rewardUpdateHeight )
        {
            $reward = $previousRewardPerBlock * ( $height - $lastInterestHeight );
            return $lastInterest + fraction( $reward, $scaleValue, $shareTokenLocked );
        }
        else
        {
            if( $lastInterestHeight > $rewardUpdateHeight )
            {
                $reward = $currentRewardPerBlock * ( $height - $lastInterestHeight );
                return $lastInterest + fraction( $reward, $scaleValue, $shareTokenLocked );
            }
            else
            {
                $rewardAfterLastInterestBeforeReawardUpdate = $previousRewardPerBlock * ( $rewardUpdateHeight - $lastInterestHeight );
                $interestAfterUpdate = $lastInterest + fraction( $rewardAfterLastInterestBeforeReawardUpdate, $scaleValue, $shareTokenLocked );
                $reward = $currentRewardPerBlock * ( $height - $rewardUpdateHeight );
                return $interestAfterUpdate + fraction( $reward, $scaleValue, $shareTokenLocked );
            }
        }
    }

    function claimCalc( $pool, $caller )
    {
        $scaleValue = calcScaleValue( $pool );
        $shareTokenLocked = $this->getTotalShareTokenLocked( $pool );
        list( $lastInterestHeight, $lastInterest ) = $this->getLastInterestInfo( $pool );
        list( $currentRewardPerBlock, $rewardUpdateHeight, $previousRewardPerBlock ) = $this->rewardInfo( $pool );
        list( $userLastInterest, $userShareTokensAmount ) = $this->getUserInterestInfo( $pool, $caller );
        $currentInterest = $this->calcInterest( $lastInterestHeight, $rewardUpdateHeight, $lastInterest, $currentRewardPerBlock, $shareTokenLocked, $previousRewardPerBlock, $scaleValue );
        $claimAmount = fraction( $userShareTokensAmount, $currentInterest - $userLastInterest, $scaleValue );
        $userNewInterest = $currentInterest;
        return [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ];
    }

    function address()
    {
        return '3P73HDkPqG15nLXevjCbmXtazHYTZbpPoPw';
    }

    function __construct( $dApp )
    {
        $this->v2 = false;
    }

    function data( $tx )
    {
        absorber( $this, $tx['data'] );
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

    function contract( $tx )
    {
        if( !$this->v2 && $tx['id'] === '5TjBrivfmEDnMr5hLDnwNLDs63dim8Y1jM8Zc59Zd9ta' )
            $this->v2 = true;
    }

    function userAvailableSWOP( $pool, $user )
    {
        return $this->getInteger( $pool . "_" . $user . "_available_SWOP", 0 );
    }

    function getUserSWOPClaimedAmount( $pool, $user )
    {
        return $this->getInteger( $pool . "_" . $user . "_SWOP_claimed_amount", 0 );
    }

    function invoke( $tx )
    {
        if( $this->v2 )
        {
            $function = $tx['call']['function'];

            if( $function === 'lockShareTokens' )
            {
                global $height;
                global $wk;

                $pool = $tx['call']['args'][0]['value'];
                $caller = $tx['sender'];
                $pmtAmount = $tx['payment'][0]['amount'];

                list( $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ) = $this->claimCalc( $pool, $caller );
                $userShareAmountNew = $userShareTokensAmount + $pmtAmount;
                $availableFundsNew = $this->userAvailableSWOP( $pool, $caller ) + $claimAmount;
                $totalShareAmount = $this->getTotalShareTokenLocked( $pool );
                $totalShareAmountNew = $totalShareAmount + $pmtAmount;
                $userClaimedAmount = $this->getUserSWOPClaimedAmount( $pool, $caller );
                $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                $this->IntegerEntry( $pool . "_" . $caller . "_last_interest", $userNewInterest );
                $this->IntegerEntry( $pool . "_" . $caller . "_share_tokens_locked", $userShareAmountNew );
                $this->IntegerEntry( $pool . "_last_interest", $currentInterest);
                $this->IntegerEntry( $pool . "_last_interest_height", $height );
                $this->IntegerEntry( $pool . "_total_share_tokens_locked", $totalShareAmountNew);
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_claimed_amount", $userClaimedAmountNew );
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_last_claimed_amount", $claimAmount );
                $this->IntegerEntry( $pool . "_" . $caller . "_available_SWOP", $availableFundsNew );
            }
            else
            if( $function === 'withdrawShareTokens' )
            {
                global $height;
                global $wk;

                $pool = $tx['call']['args'][0]['value'];
                $caller = $tx['sender'];
                $shareTokensWithdrawAmount = $tx['call']['args'][1]['value'];

                list( $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ) = $this->claimCalc( $pool, $caller );
                $userShareAmountNew = $userShareTokensAmount - $shareTokensWithdrawAmount;
                $availableFundsNew = $this->userAvailableSWOP( $pool, $caller ) + $claimAmount;
                $totalShareAmount = $this->getTotalShareTokenLocked( $pool );
                $totalShareAmountNew = $totalShareAmount - $shareTokensWithdrawAmount;
                $userClaimedAmount = $this->getUserSWOPClaimedAmount( $pool, $caller );
                $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                $this->IntegerEntry( $pool . "_" . $caller . "_last_interest", $userNewInterest );
                $this->IntegerEntry( $pool . "_" . $caller . "_share_tokens_locked", $userShareAmountNew );
                $this->IntegerEntry( $pool . "_last_interest", $currentInterest );
                $this->IntegerEntry( $pool . "_last_interest_height", $height );
                $this->IntegerEntry( $pool . "_total_share_tokens_locked", $totalShareAmountNew );
                $this->IntegerEntry( $pool . "_" . $caller . "_available_SWOP", $availableFundsNew );
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_claimed_amount", $userClaimedAmountNew );
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_last_claimed_amount", $claimAmount );
            }
            else
            if( $function === 'claim' )
            {
                global $height;
                global $wk;

                $pool = $tx['call']['args'][0]['value'];
                $caller = $tx['sender'];

                [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ] = $this->claimCalc( $pool, $caller );
                $userClaimedAmount = $this->getUserSWOPClaimedAmount( $pool, $caller );
                $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                $this->IntegerEntry( $pool . "_" . $caller . "_last_interest", $userNewInterest );
                $this->IntegerEntry( $pool . "_last_interest", $currentInterest );
                $this->IntegerEntry( $pool . "_last_interest_height", $height );
                $this->IntegerEntry( $pool . "_" . $caller . "_available_SWOP", 0 );
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_claimed_amount", $userClaimedAmountNew );
                $this->IntegerEntry( $pool . "_" . $caller . "_SWOP_last_claimed_amount", $claimAmount );
            }
            else
            {
                absorber( $this, $tx['stateChanges']['data'] );
            }

            if( WFRACTION )
            foreach( $tx['stateChanges']['data'] as $r )
            {
                $key = $r['key'];
                $value = $r['value'];

                if( !isset( $value ) )
                {
                    if( isset( $this->db[$key] ) )
                        $wk->log( 'e', "$key exists" );
                }
                else if( $this->db[$key] !== $value )
                    $wk->log( 'e', "{$this->db[$key]} !== $value" );
            }

            return;
        }

        absorber( $this, $tx['stateChanges']['data'] );
    }   
}

function dApp()
{
    static $d;

    if( !isset( $d ) )
    {
        global $dApp;
        $d = new dApp_Farming( $dApp );
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
                '*' => function( $tx ){ dApp()->invoke( $tx ); },
            ],

            governanceAddress()->address() =>
            [
                '*' => function( $tx ){ governanceAddress()->invoke( $tx ); },
            ],
        ],

        12 => 
        [
            governanceAddress()->address() => function( $tx ){ governanceAddress()->data( $tx ); },
            dApp()->address() => function( $tx ){ dApp()->data( $tx ); },
        ],

        13 => 
        [
            dApp()->address() => function( $tx ){ dApp()->contract( $tx ); },
        ],
    ];
}

snapshot();
$wk->log( 'finish' );
