<?php

require __DIR__ . '/common.php';

$dApp = '3P73HDkPqG15nLXevjCbmXtazHYTZbpPoPw';
$dAppVoting = '3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS';

// globals
$bdb = [];
$pools = [];
$users = [];

// keys
$keyShareTokensLocked = "_total_share_tokens_locked";
$keyLastInterest = "_last_interest";
$keyCurrentReward = "_current_reward";
$keyRewardUpdateHeight = "_reward_update_height";
$keyPreviousReward = "_previous_reward";
$keyLastInterestHeight = "_last_interest_height";
$keyUserShareTokensLocked = "_share_tokens_locked";
$keyUserLastInterest = "_last_interest";
$keySWOPid = "SWOP_id";
$keyUserSWOPClaimedAmount = "_SWOP_claimed_amount";
$keyUserSWOPLastClaimedAmount = "_SWOP_last_claimed_amount";
$keyAvailableSWOP = "_available_SWOP";
$keyFarmingStartHeight = "farming_start_height";

$v2_txid = '5TjBrivfmEDnMr5hLDnwNLDs63dim8Y1jM8Zc59Zd9ta';
$v2 = false;

// v2
$keyRewardUpdateHeightV2 = "reward_update_height";
$keyRewardPoolFractionCurrent = "_current_pool_fraction_reward";
$keyRewardPoolFractionPrevious = "_previous_pool_fraction_reward";
$keyTotalRewardPerBlockCurrent = "total_reward_per_block_current";
$keyTotalRewardPerBlockPrevious = "total_reward_per_block_previous";
$totalVoteShare = 10000000000;

$SWOP_per_block_emission = 0;

$oneWeekInBlock = 10106;

$txs = [];
$qs = [ getTxs( $wk, $dApp ), getTxs( $wk, $dAppVoting ) ];
dAppReproduce( $wk, $qs, getFunctions() );

function validator()
{
    global $wk;
    global $pools;
    global $users;

    $wk->log( 'validator: pool total SWOP vs. user total SWOP' );
    $totalPool = 0;
    $totalUser = 0;
    foreach( $pools as $pool => $info )
    {
        $poolSWOPs = calculatePoolGeneration( $pool );

        $usersSWOPs = 0;
        foreach( $users as $user => $unused )
        {
            $userSWOPs = calculateUserPoolTotal( $user, $pool );
            $usersSWOPs += $userSWOPs;
        }

        $diff = $poolSWOPs - $usersSWOPs;
        $wk->log( $pool . ': ' . amount( $poolSWOPs, 8 ) . ' === ' . amount( $usersSWOPs, 8 ) . ' (' . amount( $diff, 8 ) . ')' );
        $totalPool += $poolSWOPs;
        $totalUser += $usersSWOPs;
    }

    $diff = $totalPool - $totalUser;
    $wk->log( 'TOTAL: ' . amount( $totalPool, 8 ) . ' === ' . amount( $totalUser, 8 ) . ' (' . amount( $diff, 8 ) . ')' );
}

validator();

function calculatePoolGeneration( $pool )
{
    global $pools;
    global $height;

    global $keyCurrentReward;
    global $keyRewardUpdateHeight;
    global $keyPreviousReward;

    global $dApp;
    global $keyFarmingStartHeight;
    global $oneWeekInBlock;

    if( !isset( $pools[$pool]['start'] ) )
        return 0;

    $poolStart = $pools[$pool]['start'];
    $lastInterestHeight = 0;
    $totalReward = 0;

    $index = $pools[$pool]['index'];
    for( $i = 0; $i <= $index; ++$i )
    {
        $rewardInfo = $pools[$pool][$i];

        $currentRewardPerBlock = $rewardInfo[$keyCurrentReward];
        $rewardUpdateHeight = $rewardInfo[$keyRewardUpdateHeight];
        $previousRewardPerBlock = $rewardInfo[$keyPreviousReward];

        if( $rewardUpdateHeight === 0 )
            continue;

        $reward = $previousRewardPerBlock * ( $rewardUpdateHeight - $lastInterestHeight );
        $totalReward += $reward;

        $lastInterestHeight = $rewardUpdateHeight;

        if( $lastInterestHeight < $poolStart )
            $lastInterestHeight = $poolStart;
    }

    global $height;

    $reward = $currentRewardPerBlock * ( $height - $lastInterestHeight );
    $totalReward += $reward;

    return $totalReward;
}

function calculateUserTotal( $user )
{
    global $pools;
    $total = 0;
    foreach( $pools as $pool => $unused )
        $total += calculateUserPoolTotal( $user, $pool );
    return $total;
}

function calculateUserPoolTotal( $caller, $pool )
{
    [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ] = claimCalc( $pool, $caller );
    $userClaimedAmount = getUserSWOPClaimedAmount( $pool, $caller );
    return $userClaimedAmount + $claimAmount;
}

function rewardInfo( $pool )
{
    global $dAppVoting;

    global $keyCurrentReward;
    global $keyRewardUpdateHeight;
    global $keyPreviousReward;

    global $v2;
    global $keyRewardUpdateHeightV2;
    global $keyRewardPoolFractionCurrent;
    global $keyRewardPoolFractionPrevious;
    global $keyTotalRewardPerBlockCurrent;
    global $keyTotalRewardPerBlockPrevious;

    if( $v2 )
    {
        $rewardUpdateHeight = getInteger( $dAppVoting, $keyRewardUpdateHeightV2 );
        $totalRewardPerBlockCurrent = getInteger( $dAppVoting, $keyTotalRewardPerBlockCurrent );
        $totalRewardPerBlockPrevious = getInteger( $dAppVoting, $keyTotalRewardPerBlockPrevious );
        $rewardPoolFractionCurrent = getInteger( $dAppVoting, $pool . $keyRewardPoolFractionCurrent );
        $rewardPoolFractionPrevious = getInteger( $dAppVoting, $pool . $keyRewardPoolFractionPrevious );

        global $totalVoteShare;
        $rewardPoolCurrent = (int)fraction( $totalRewardPerBlockCurrent, $rewardPoolFractionCurrent, $totalVoteShare );
        $rewardPoolPrevious = (int)fraction( $totalRewardPerBlockPrevious, $rewardPoolFractionPrevious, $totalVoteShare );

        return [ $rewardPoolCurrent, $rewardUpdateHeight, $rewardPoolPrevious ];
    }

    $currentReward = getInteger( $dAppVoting, $pool . $keyCurrentReward );
    $rewardUpdateHeight = getInteger( $dAppVoting, $pool . $keyRewardUpdateHeight );
    $rewardPreviousAmount = getInteger( $dAppVoting, $pool . $keyPreviousReward );

    return [ $currentReward, $rewardUpdateHeight, $rewardPreviousAmount ];
}

function farmingStartHeight()
{
    global $bdb;
    global $dApp;
    global $keyFarmingStartHeight;
    if( !isset( $bdb[$dApp][$keyFarmingStartHeight] )) // https://w8io.ru/tx/3FYUuDKUkit5ah53Epmm4vxw5JwFBiA6GrN5zmRZ7Me2
        return $bdb[$dApp]['farming_start_key'];
    return $bdb[$dApp][$keyFarmingStartHeight];
}

function getInteger( $dApp, $key, $default = null )
{
    global $bdb;
    if( isset( $default ) && !isset( $bdb[$dApp][$key] ) )
        return $default;
    return $bdb[$dApp][$key];
}

function IntegerEntry( $key, $value )
{
    global $bdb;
    global $dApp;

    $bdb[$dApp][$key] = $value;
}

function getTotalShareTokenLocked( $pool )
{
    global $dApp;
    global $keyShareTokensLocked;

    return getInteger( $dApp, $pool . $keyShareTokensLocked );
}

function calcInterest( $lastInterestHeight, $rewardUpdateHeight, $lastInterest, $currentRewardPerBlock, $shareTokenLocked, $previousRewardPerBlock, $scaleValue )
{
    if( $shareTokenLocked === 0 )
        return 0;

    global $v2;

    if( $v2 )
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

    if( WFIX )
    {
        global $height;

        if( $lastInterestHeight > $rewardUpdateHeight )
        {
            $reward = $currentRewardPerBlock * ( $height - $lastInterestHeight );
            return $lastInterest + fraction( $reward, $scaleValue, $shareTokenLocked );
        }
        else
        {
            if( $rewardUpdateHeight > $height )
                $rewardUpdateHeight = $height;

            $rewardAfterLastInterestBeforeReawardUpdate = $previousRewardPerBlock * ( $rewardUpdateHeight - $lastInterestHeight );
            $interestAfterUpdate = $lastInterest + fraction( $rewardAfterLastInterestBeforeReawardUpdate, $scaleValue, $shareTokenLocked );
            $reward = $currentRewardPerBlock * ( $height - $rewardUpdateHeight );
            return $interestAfterUpdate + fraction( $reward, $scaleValue, $shareTokenLocked );
        }
    }
    else
    {
        global $height;

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

function getLastInterestInfo( $pool )
{
    global $dApp;
    global $keyLastInterest;
    global $keyLastInterestHeight;
    global $keyFarmingStartHeight;
    global $oneWeekInBlock;
    
    $farmingPreStartHeight = getInteger( $dApp, $keyFarmingStartHeight ) - $oneWeekInBlock;

    $lastInterest = getInteger( $dApp, $pool . $keyLastInterest );
    $lastInterestHeight = getInteger( $dApp, $pool . $keyLastInterestHeight, $farmingPreStartHeight );
    return [ $lastInterestHeight, $lastInterest ];
}

function getUserInterestInfo( $pool, $userAddress )
{
    global $dApp;
    global $keyLastInterest;
    global $keyUserLastInterest;
    global $keyUserShareTokensLocked;

    $lastInterest = getInteger( $dApp, $pool . $keyLastInterest );
    $userLastInterest = getInteger( $dApp, $pool . "_" . $userAddress . $keyUserLastInterest, $lastInterest );
    $userShare = getInteger( $dApp, $pool . "_" . $userAddress . $keyUserShareTokensLocked, 0 );

    return [ $userLastInterest, $userShare ];
}

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

function claimCalc( $pool, $caller )
{
    $scaleValue = calcScaleValue( $pool );
    $shareTokenLocked = getTotalShareTokenLocked( $pool );
    list( $lastInterestHeight, $lastInterest ) = getLastInterestInfo( $pool );
    list( $currentRewardPerBlock, $rewardUpdateHeight, $previousRewardPerBlock ) = rewardInfo( $pool );
    list( $userLastInterest, $userShareTokensAmount ) = getUserInterestInfo( $pool, $caller );
    $currentInterest = calcInterest( $lastInterestHeight, $rewardUpdateHeight, $lastInterest, $currentRewardPerBlock, $shareTokenLocked, $previousRewardPerBlock, $scaleValue );
    $claimAmount = fraction( $userShareTokensAmount, $currentInterest - $userLastInterest, $scaleValue );
    $userNewInterest = $currentInterest;
    return [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ];
}

function userAvailableSWOP( $pool, $user )
{
    global $dApp;
    global $keyAvailableSWOP;

    return getInteger( $dApp, $pool . "_" . $user . $keyAvailableSWOP, 0 );
}

function getUserSWOPClaimedAmount( $pool, $user )
{
    global $dApp;
    global $keyUserSWOPClaimedAmount;

    return getInteger( $dApp, $pool . "_" . $user . $keyUserSWOPClaimedAmount, 0 );
}

function getUserSWOPLastClaimedAmount( $pool, $user )
{
    global $dApp;
    global $keyUserSWOPLastClaimedAmount;

    return getInteger( $dApp, $pool . "_" . $user . $keyUserSWOPLastClaimedAmount, 0 );
}

function dataVoting( $tx )
{
    global $wk;
    
    global $bdb;
    global $dAppVoting;

    global $keyCurrentReward;
    global $keyRewardUpdateHeight;
    global $keyPreviousReward;

    global $keyLastInterestHeight;
    global $keyLastInterest;

    global $keyRewardUpdateHeightV2;
    global $keyRewardPoolFractionCurrent;
    global $keyRewardPoolFractionPrevious;
    global $keyTotalRewardPerBlockCurrent;
    global $keyTotalRewardPerBlockPrevious;

    global $pools;
    $rollbacks = [];

    foreach( $tx['data'] as $ktv )
    {
        if( !isset( $ktv['type'] ) )
            continue;

        $key = $ktv['key'];
        $type = $ktv['type'];
        $value = $ktv['value'];

        if( $type !== 'integer' )
            exit( $wk->log( 'e', 'unexpected data (' . $tx['id'] . ')' ) );

        if( $key === 'SWOP_per_block_emission' )
        {
            global $SWOP_per_block_emission;
            $SWOP_per_block_emission = $value;
            continue;
        }

        if( $key === 'last_interest' ||
            $key === 'total_SWOP_amount' )
        {
            continue; // fix governance
        }

        if( $key === $keyRewardUpdateHeightV2 ||
            $key === $keyTotalRewardPerBlockCurrent ||
            $key === $keyTotalRewardPerBlockPrevious )
        {
            $bdb[$dAppVoting][$key] = $value;
            continue; // v2
        }

        $pool = substr( $key, 0, 35 );
        $keyPart = substr( $key, 35 );

        $newFormat = false;

        switch( $keyPart )
        {
            case $keyRewardUpdateHeight:
                if( WFIX )
                {
                    global $height;
                    if( $height > $value )
                    {
                        $value = $height + 1;
                    }
                }                            
            case $keyPreviousReward:
            case $keyCurrentReward:
                if( !isset( $pools[$pool]['index'] ) )
                    $pools[$pool]['index'] = 0;

                $index = $pools[$pool]['index'];
                if( isset( $pools[$pool][$index][$keyPart] ) )
                {
                    ++$index;
                    $pools[$pool]['index'] = $index;
                }

                $pools[$pool][$index][$keyPart] = $value;
                break;
            
            case $keyLastInterestHeight:
            case $keyLastInterest:
                break;
            case '_SWOP_amount':
            case $keyLastInterest:
                break;
            case $keyRewardPoolFractionCurrent:
            case $keyRewardPoolFractionPrevious:
                break;
            default:
                exit( 'unknown key: '. $keyPart );
        }

        $bdb[$dAppVoting][$key] = $value;
    }

    if( 0 && $newFormat )
    {
        foreach( $pools as $pool => $unused )
        {
            $totalRewardPerBlockCurrent = getInteger( $dAppVoting, $keyTotalRewardPerBlockCurrent );
            $totalRewardPerBlockPrevious = getInteger( $dAppVoting, $keyTotalRewardPerBlockPrevious );
            $rewardPoolFractionCurrent = getInteger( $dAppVoting, $pool . $keyRewardPoolFractionCurrent );
            $rewardPoolFractionPrevious = getInteger( $dAppVoting, $pool . $keyRewardPoolFractionPrevious );

            global $totalVoteShare;
            $rewardPoolCurrent = (int)fraction( $totalRewardPerBlockCurrent, $rewardPoolFractionCurrent, $totalVoteShare );
            $rewardPoolPrevious = (int)fraction( $totalRewardPerBlockPrevious, $rewardPoolFractionPrevious, $totalVoteShare );

            $i = $pools[$pool]['index'];
            if( isset( $pools[$pool][$i][$keyCurrentReward] ) && $pools[$pool][$i][$keyCurrentReward] !== $rewardPoolCurrent )
                $wk->log( 'e', 'bad rewardPoolCurrent change at ' . $tx['id'] );
            if( isset( $pools[$pool][$i][$keyPreviousReward] ) && $pools[$pool][$i][$keyPreviousReward] !== $rewardPoolPrevious )
                $wk->log( 'e', 'bad rewardPoolCurrent change at ' . $tx['id'] );
            
            $pools[$pool][$i][$keyCurrentReward] = $rewardPoolCurrent;
            $pools[$pool][$i][$keyPreviousReward] = $rewardPoolPrevious;
        }
    }

    if( WFIX )
    {
        foreach( $pools as $pool => $unused )
        {
            $lastRewardPerBlock = 0;
            $index = $pools[$pool]['index'];
            for( $i = 0; $i <= $index; ++$i )
            {
                $rewardInfo = $pools[$pool][$i];

                $currentRewardPerBlock = $rewardInfo[$keyCurrentReward];
                $previousRewardPerBlock = $rewardInfo[$keyPreviousReward];

                if( $previousRewardPerBlock !== $lastRewardPerBlock )
                {
                    global $wk;
                    $pools[$pool][$i][$keyPreviousReward] = $lastRewardPerBlock;
                    $bdb[$dAppVoting][$pool . $keyPreviousReward] = $lastRewardPerBlock;
                    $wk->log( 'lastRewardPerBlock fixed = ' . $lastRewardPerBlock . ' (' . $tx['id'] . ')' );
                }

                $lastRewardPerBlock = $currentRewardPerBlock;
            }
        }
    }
}

function getFunctions()
{
    global $dApp;
    global $dAppVoting;

    return
    [
        16 => 
        [
            $dAppVoting =>
            [   
                'lockSWOP' => function( $tx ){},
                'withdrawSWOP' => function( $tx ){},
                'airDrop' => function( $tx ){},
                'claimAndStakeSWOP' => function( $tx ){},
                'claimAndWithdrawSWOP' => function( $tx ){},
                'shutdown' => function( $tx ){},
                'activate' => function( $tx ){},
                'updateWeights' => function( $tx )
                {
                    $tx['data'] = $tx['stateChanges']['data'];
                    dataVoting( $tx );
                },
            ],
            $dApp =>
            [   
                'initPoolShareFarming' => function( $tx )
                {
                    global $bdb;
                    global $dApp;

                    global $keyShareTokensLocked;
                    global $keyLastInterest;
                    global $keyCurrentReward;
                    global $keyRewardUpdateHeight;
                    global $keyPreviousReward;

                    $pool = $tx['call']['args'][0]['value'];

                    list( $currentReward, $rewardUpdateHeight, $previousRewardPerBlock ) = rewardInfo( $pool );

                    $bdb[$dApp][$pool . $keyShareTokensLocked] = 0;
                    $bdb[$dApp][$pool . $keyLastInterest] = 0;
                    $bdb[$dApp][$pool . $keyCurrentReward] = $currentReward;
                    $bdb[$dApp][$pool . $keyRewardUpdateHeight] = $rewardUpdateHeight;
                    $bdb[$dApp][$pool . $keyPreviousReward] = $previousRewardPerBlock;
                },

                'lockShareTokens' => function( $tx )
                {
                    global $dApp;
                    global $height;

                    global $keyUserShareTokensLocked;
                    global $keyUserLastInterest;
                    global $keyLastInterest;
                    global $keyShareTokensLocked;
                    global $keyAvailableSWOP;

                    $pool = $tx['call']['args'][0]['value'];
                    $caller = $tx['sender'];
                    $pmtAmount = $tx['payment'][0]['amount'];

                    global $users;
                    $users[$caller] = true;

                    if( $height < farmingStartHeight() )
                    {
                        global $keyUserLastInterest;
                        global $keyUserShareTokensLocked;
                        global $keyLastInterest;
                        global $keyShareTokensLocked;
                        global $keyAvailableSWOP;

                        $userShareTokensAmount = getInteger( $dApp, $pool . "_" . $caller . $keyUserShareTokensLocked, 0 );
                        $userNewInterest = 0;
                        $userShareAmountNew = $userShareTokensAmount + $pmtAmount;
                        $totalShareAmount = getTotalShareTokenLocked( $pool );
                        $totalShareAmountNew = $totalShareAmount + $pmtAmount;

                        IntegerEntry( $pool . "_" . $caller . $keyUserLastInterest, $userNewInterest );
                        IntegerEntry( $pool . "_" . $caller . $keyUserShareTokensLocked, $userShareAmountNew );
                        IntegerEntry( $pool . $keyLastInterest, 0 );
                        IntegerEntry( $pool . $keyShareTokensLocked, $totalShareAmountNew );
                        IntegerEntry( $pool . "_" . $caller . $keyAvailableSWOP, 0 );
                    }
                    else
                    {
                        global $keyUserLastInterest;
                        global $keyUserShareTokensLocked;
                        global $keyLastInterest;
                        global $keyLastInterestHeight;
                        global $keyShareTokensLocked;
                        global $keyUserSWOPClaimedAmount;
                        global $keyUserSWOPLastClaimedAmount;
                        global $keyAvailableSWOP;

                        list( $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ) = claimCalc( $pool, $caller );
                        $userShareAmountNew = $userShareTokensAmount + $pmtAmount;
                        $availableFundsNew = userAvailableSWOP( $pool, $caller ) + $claimAmount;
                        $totalShareAmount = getTotalShareTokenLocked( $pool );
                        $totalShareAmountNew = $totalShareAmount + $pmtAmount;
                        $userClaimedAmount = getUserSWOPClaimedAmount( $pool, $caller );
                        $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                        IntegerEntry( $pool . "_" . $caller . $keyUserLastInterest, $userNewInterest );
                        IntegerEntry( $pool . "_" . $caller . $keyUserShareTokensLocked, $userShareAmountNew );
                        IntegerEntry( $pool . $keyLastInterest, $currentInterest);
                        IntegerEntry( $pool . $keyLastInterestHeight, $height );
                        IntegerEntry( $pool . $keyShareTokensLocked, $totalShareAmountNew);
                        IntegerEntry( $pool . "_" . $caller . $keyUserSWOPClaimedAmount, $userClaimedAmountNew );
                        IntegerEntry( $pool . "_" . $caller . $keyUserSWOPLastClaimedAmount, $claimAmount );
                        IntegerEntry( $pool . "_" . $caller . $keyAvailableSWOP, $availableFundsNew );
                    }

                    if( $totalShareAmountNew !== 0 )
                    {
                        global $pools;
                        global $height;
                        if( !isset( $pools[$pool]['start'] ) )
                        {
                            $pools[$pool]['start'] = $height;

                            global $v2;
                            if( $v2 && !isset( $pools[$pool]['index'] ) )
                            {
                                $pools[$pool]['index'] = 0;
                                $pools[$pool][0]['_current_reward'] = 0;
                                $pools[$pool][0]['_reward_update_height'] = 0;
                                $pools[$pool][0]['_previous_reward'] = 0;
                            }
                        }
                    }
                },
                'withdrawShareTokens' => function( $tx )
                {
                    global $dApp;
                    global $height;

                    global $keyUserShareTokensLocked;
                    global $keyUserLastInterest;
                    global $keyLastInterest;
                    global $keyShareTokensLocked;
                    global $keyAvailableSWOP;

                    $pool = $tx['call']['args'][0]['value'];
                    $shareTokensWithdrawAmount = $tx['call']['args'][1]['value'];
                    $caller = $tx['sender'];

                    if( $height < farmingStartHeight() )
                    {
                        $userShareTokensAmount = getInteger( $dApp, $pool . "_" . $caller . $keyUserShareTokensLocked, 0 );
                        $userNewInterest = 0;
                        $userShareAmountNew = $userShareTokensAmount - $shareTokensWithdrawAmount;
                        $totalShareAmount = getTotalShareTokenLocked( $pool );
                        $totalShareAmountNew = $totalShareAmount - $shareTokensWithdrawAmount;

                        IntegerEntry( $pool . "_" . $caller . $keyUserLastInterest, $userNewInterest );
                        IntegerEntry( $pool . "_" . $caller . $keyUserShareTokensLocked, $userShareAmountNew );
                        IntegerEntry( $pool . $keyLastInterest, 0 );
                        IntegerEntry( $pool . $keyShareTokensLocked, $totalShareAmountNew );
                        IntegerEntry( $pool . "_" . $caller . $keyAvailableSWOP, 0 );
                    }
                    else
                    {
                        global $keyUserLastInterest;
                        global $keyUserShareTokensLocked;
                        global $keyLastInterest;
                        global $keyLastInterestHeight;
                        global $keyShareTokensLocked;
                        global $keyAvailableSWOP;
                        global $keyUserSWOPClaimedAmount;
                        global $keyUserSWOPLastClaimedAmount;

                        list( $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ) = claimCalc( $pool, $caller );
                        $userShareAmountNew = $userShareTokensAmount - $shareTokensWithdrawAmount;
                        $availableFundsNew = userAvailableSWOP( $pool, $caller ) + $claimAmount;
                        $totalShareAmount = getTotalShareTokenLocked( $pool );
                        $totalShareAmountNew = $totalShareAmount - $shareTokensWithdrawAmount;
                        $userClaimedAmount = getUserSWOPClaimedAmount( $pool, $caller );
                        $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                        IntegerEntry( $pool . "_" . $caller . $keyUserLastInterest, $userNewInterest );
                        IntegerEntry( $pool . "_" . $caller . $keyUserShareTokensLocked, $userShareAmountNew );
                        IntegerEntry( $pool . $keyLastInterest, $currentInterest );
                        IntegerEntry( $pool . $keyLastInterestHeight, $height );
                        IntegerEntry( $pool . $keyShareTokensLocked, $totalShareAmountNew );
                        IntegerEntry( $pool . "_" . $caller . $keyAvailableSWOP, $availableFundsNew );
                        IntegerEntry( $pool . "_" . $caller . $keyUserSWOPClaimedAmount, $userClaimedAmountNew );
                        IntegerEntry( $pool . "_" . $caller . $keyUserSWOPLastClaimedAmount, $claimAmount );
                    }
                },

                'claim' => function( $tx )
                {
                    global $dApp;
                    global $height;

                    $pool = $tx['call']['args'][0]['value'];
                    $caller = $tx['sender'];

                    [ $userNewInterest, $currentInterest, $claimAmount, $userShareTokensAmount ] = claimCalc( $pool, $caller );
                    $availableFund = userAvailableSWOP( $pool, $caller ) + $claimAmount;
                    $userClaimedAmount = getUserSWOPClaimedAmount( $pool, $caller );
                    $userClaimedAmountNew = $userClaimedAmount + $claimAmount;

                    global $keyUserLastInterest;
                    global $keyLastInterest;
                    global $keyLastInterestHeight;
                    global $keyAvailableSWOP;
                    global $keyUserSWOPClaimedAmount;
                    global $keyUserSWOPLastClaimedAmount;

                    IntegerEntry( $pool . "_" . $caller . $keyUserLastInterest, $userNewInterest );
                    IntegerEntry( $pool . $keyLastInterest, $currentInterest );
                    IntegerEntry( $pool . $keyLastInterestHeight, $height );
                    IntegerEntry( $pool . "_" . $caller . $keyAvailableSWOP, 0 );
                    IntegerEntry( $pool . "_" . $caller . $keyUserSWOPClaimedAmount, $userClaimedAmountNew );
                    IntegerEntry( $pool . "_" . $caller . $keyUserSWOPLastClaimedAmount, $claimAmount );

                    if( !WFIX && WFRACTION && $userClaimedAmountNew !== $tx['stateChanges']['data'][4]['value'] )
                    {
                        global $wk;
                        exit( $wk->log( 'e', 'userClaimedAmountNew does not match stateChanges (' . $tx['id'] . ')' ) );
                    }
                },

                'init' => function( $tx )
                {
                    static $init;

                    if( isset( $init ) )
                    {
                        global $wk;
                        exit( $wk->log( 'e', 'double init' ) );
                    }

                    $init = true;
                },
            ],
        ],
        12 =>
        [
            $dAppVoting => function( $tx ){ dataVoting( $tx ); },

            $dApp => function( $tx )
            {
                global $wk;

                if( WFIX )
                {
                    if( $tx['id'] === '62ncT9GnqXo8k2kSijCJRZvJjiU4G75KUxEK7GAmtEwq' ||
                        $tx['id'] === '8c8t7fGwH1zesXedYz4kdFzyecQFqRn3Pw4HRaN6K6We' )
                        return;
                }

                global $bdb;
                global $dApp;

                foreach( $tx['data'] as $ktv )
                {
                    $key = $ktv['key'];
                    $value = $ktv['value'];

                    if( !isset( $ktv['type'] ) && $value === null )
                    {
                        unset( $bdb[$dApp][$key] );
                        continue;                                            
                    }

                    $type = $ktv['type'];
                    if( $type !== 'integer' )
                        exit( $wk->log( 'e', 'unexpected data (' . $tx['id'] . ')' ) );

                    $bdb[$dApp][$key] = $value;
                }
            },
        ],
        13 =>
        [
            $dApp => function( $tx )
            {
                global $v2_txid;
                if( $tx['id'] === $v2_txid )
                {
                    global $v2;
                    $v2 = true;
                    global $working;
                    $working = false;
                    global $height;
                    $height = $tx['height'];
                }
            },
        ],
    ];
}
