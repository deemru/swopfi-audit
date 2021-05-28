<?php

define( 'WFRACTION', true );

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, dApp()->address() ), getTxs( $wk, govAddr()->address() ) ], getFunctions() );

class dApp_Governance_Voting
{
    function address()
    {
        return '3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS';
    }

    function __construct()
    {
        $this->pools = [];
        $this->v2 = false;
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
            global $wk;
            $wk->log( 'updateWeigth at '. $tx['id'] );

            $previousPools = $tx['call']['args'][0]['value'];
            $previousRewards = $tx['call']['args'][1]['value'];
            $currentPools = $tx['call']['args'][2]['value'];
            $currentRewards = $tx['call']['args'][3]['value'];
            $rewardUpdateHeight = $tx['call']['args'][4]['value'];

            if( count( $previousPools ) !== count( $previousRewards ) ||
                count( $currentPools ) !== count( $currentRewards ) )
                $wk->log( 'e', 'pools count?' );

            $n = count( $previousPools );
            for( $i = 0; $i < $n; ++$i )
            {
                $k = $previousPools[$i]['value'];
                $v = $previousRewards[$i]['value'];
                $this->prevs[$k] = $v;

                if( isset( $this->currs[$k] ) && $this->currs[$k] !== $v )
                    $wk->log( 'e', "wrong previous: {$this->currs[$k]} !== $v" );
            }

            $n = count( $currentPools );
            for( $i = 0; $i < $n; ++$i )
            {
                $k = $currentPools[$i]['value'];
                $v = $currentRewards[$i]['value'];
                $this->currs[$k] = $v;

                if( $v === 0 )
                    continue;

                if( !dApp()->v2 )
                {
                    $total = dApp()->getInteger( dApp()->kTotalVoteSWOP );
                    $poolVotes = dApp()->getInteger( $k . dApp()->kPoolVoteSWOP );

                    $vv = round( $poolVotes * 10000000000 / $total );
                    if( abs( $v - $vv ) > 1 )
                        $wk->log( 'w', "$k: $v !== $vv" );
                }
                else
                {
                    $data = explode( '_', dApp()->db[dApp()->kTotalStruc] );
                    $totalVoteSWOP = (int)$data[0];
                    $totalActiveSWOP = (int)$data[1];
                    $totalPeriod = (int)$data[2];

                    $data = explode( '_', dApp()->db[$k . dApp()->kPoolStruc] );
                    $poolVoteSWOP = (int)$data[0];
                    $poolActiveSWOP = (int)$data[1];
                    $poolPeriod = (int)$data[2];

                    $vv = round( $poolActiveSWOP * 10000000000 / $totalActiveSWOP );
                    if( abs( $v - $vv ) > 1 )
                        $wk->log( 'w', "$k: $v !== $vv" );
                }
            }
        }

        foreach( $tx['stateChanges']['data'] as $r )
        {
            $key = $r['key'];
            $value = $r['value'];

            $this->db[$key] = $value;
        }
    }

    function data( $tx )
    {
        foreach( $tx['data'] as $r )
        {
            $key = $r['key'];
            $value = $r['value'];

            $this->db[$key] = $value;
        }
    }
}

function govAddr()
{
    static $d;
    if( !isset( $d ) )
        $d = new dApp_Governance_Voting;
    return $d;
}

class dApp_Voting
{
    function address()
    {
        return '3PQZWxShKGRgBN1qoJw6B4s9YWS9FneZTPg';
    }

    function __construct( $dApp )
    {
        $this->pools = [];

        $this->kUserPoolVoteSWOP = "_vote";
        $this->kUserTotalVoteSWOP = "_user_total_SWOP_vote";
        $this->kPoolVoteSWOP = "_vote_SWOP";
        $this->kTotalVoteSWOP = "total_vote_SWOP";

        //v2
        $this->v2 = false;

        $this->kUserPoolStruc = "_user_pool_struc";
        $this->kUserTotalStruc = "_user_total_struc";
        $this->kPoolStruc = "_pool_struc";
        $this->kTotalStruc = "total_struc";

        $this->kStartHeight = "start_height";
        $this->kBasePeriod = "base_period";
        $this->kPeriodLength = "period_length";
        $this->kDurationFullVotePower = "duration_full_vote_power";
        $this->kMinVotePower = "min_vote_power";
    }

    function data( $tx )
    {
        foreach( $tx['data'] as $r )
        {
            $key = $r['key'];
            $value = $r['value'];

            $this->db[$key] = $value;
        }
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
        if( !$this->v2 && $tx['id'] === 'Dwo8iR9nSH62MbuYG8JQPcRMzsvktEnPaRJrvdqh178L' )
            $this->v2 = true;
    }

    function v2_prepare()
    {
        global $height;

        $this->basePeriod = $this->getInteger( $this->kBasePeriod );
        $this->startHeight = $this->getInteger( $this->kStartHeight );
        $this->periodLength = $this->getInteger( $this->kPeriodLength );
        $this->durationFullVotePower = $this->getInteger( $this->kDurationFullVotePower );
        $this->minVotePower = $this->getInteger( $this->kMinVotePower );

        $this->currPeriod = $this->basePeriod + intdiv( $height - $this->startHeight, $this->periodLength );
    }

    function votingCoef()
    {
        global $height;

        $votingDuration = $height - ( $this->startHeight + $this->currPeriod * $this->periodLength );
        if( $votingDuration < $this->durationFullVotePower )
            return 100000000;
      
        $x1 = $this->durationFullVotePower;
        $y1 = 100000000;
        $x2 = $this->periodLength;
        $y2 = $this->minVotePower;
        $k = ( $y2 - $y1 ) * 100000000 / ( $x2 - $x1 );
        $k = (int)floor( $k );
        $b = $y1 * 100000000 - $k * $x1;
        $ret1 = $votingDuration * $k / 100000000;
        $ret2 = $b / 100000000;
        $ret = (int)( floor( $ret1 ) + (int)( $ret2 ) );

        return $ret;
    }

    function votePoolWeight( $tx )
    {
        global $wk;
        global $height;

        $caller = $tx['sender'];
        $poolAddresses = $tx['call']['args'][0]['value'];
        $poolVotes = $tx['call']['args'][1]['value'];

        if( !$this->v2 )
        {
            $totalVoteSWOP = $this->getInteger( $this->kTotalVoteSWOP, 0 );
            $userTotalVoteSWOP = $this->getInteger( $caller . $this->kUserTotalVoteSWOP, 0 );
            $userSWOPinGovernance = govAddr()->getInteger( $caller . "_SWOP_amount" );

            $n = count( $poolAddresses );
            if( $n > 8 )
                exit( "$n > 8" );

            $userVoteDiffSWOP = 0;
            for( $i = 0; $i < $n; ++$i )
            {
                $poolAddress = $poolAddresses[$i]['value'];
                $poolVote = $poolVotes[$i]['value'];

                $userPoolVoteSWOP = $this->getInteger( $caller . "_" . $poolAddress . $this->kUserPoolVoteSWOP, 0 );
                $poolVoteDiffSWOP = $poolVote - $userPoolVoteSWOP;
                $userVoteDiffSWOP += $poolVoteDiffSWOP;
            }

            $userTotalVoteSWOPNew = $userTotalVoteSWOP + $userVoteDiffSWOP;
            $totalVoteSWOPnew = $totalVoteSWOP + $userVoteDiffSWOP;
        
            for( $i = 0; $i < $n; ++$i )
            {
                $poolAddress = $poolAddresses[$i]['value'];
                $poolVote = $poolVotes[$i]['value'];

                $userPoolVoteSWOP = $this->getInteger( $caller . "_" . $poolAddress . $this->kUserPoolVoteSWOP, 0 );
                $poolVoteDiffSWOP = $poolVote - $userPoolVoteSWOP;
                $poolVoteSWOP = $this->getInteger( $poolAddress . $this->kPoolVoteSWOP, 0 );
                $poolVoteSWOPnew = $poolVoteSWOP + $poolVoteDiffSWOP;
                $this->IntegerEntry( $poolAddress . $this->kPoolVoteSWOP, $poolVoteSWOPnew );
            }

            for( $i = 0; $i < $n; ++$i )
            {
                $poolAddress = $poolAddresses[$i]['value'];
                $poolVote = $poolVotes[$i]['value'];
                $this->IntegerEntry( $caller . "_"  . $poolAddress  . $this->kUserPoolVoteSWOP, $poolVote );
            }

            $this->IntegerEntry( $caller . $this->kUserTotalVoteSWOP, $userTotalVoteSWOPNew );
            $this->IntegerEntry( $this->kTotalVoteSWOP, $totalVoteSWOPnew );

            if( $userTotalVoteSWOPNew > $userSWOPinGovernance )
                $wk->log( 'e', "$userTotalVoteSWOPNew > $userSWOPinGovernance" );
        }
        else
        {
            $this->v2_prepare();

            $poolAddress = $poolAddresses[0]['value'];
            $userPoolVoteSWOPnew = $poolVotes[0]['value'];
            
            $userSWOPinGovernance = govAddr()->getInteger( $caller . "_SWOP_amount" );

            $key = $caller . "_" . $poolAddress . $this->kUserPoolStruc;
            if( isset( $this->db[$key] ) )
            {
                $data = explode( '_', $this->db[$key] );
                $userPoolVoteSWOP = (int)$data[0];
                $userPoolActiveVoteSWOP = (int)$data[1];
                $userPoolVotePeriod = (int)$data[2];
                $userPoolFreezeSWOP = (int)$data[3];
            }
            else
            {
                $uPoolVoteSWOP = $this->getInteger( $caller . "_" . $poolAddress . $this->kUserPoolVoteSWOP, 0 );
                $userPoolVoteSWOP = $uPoolVoteSWOP;
                $userPoolActiveVoteSWOP = $uPoolVoteSWOP;
                $userPoolVotePeriod = 0;
                $userPoolFreezeSWOP = 0;
            }

            $key = $caller . $this->kUserTotalStruc;
            if( isset( $this->db[$key] ) )
            {
                $data = explode( '_', $this->db[$key] );
                $userTotalVoteSWOP = (int)$data[0];
                $userUnvoted = (int)$data[1];
                $userUnvotedPeriod = (int)$data[2];
            }
            else
            {
                $uPoolTotalSWOP = $this->getInteger( $caller . $this->kUserTotalVoteSWOP, 0 );
                $userTotalVoteSWOP = $uPoolTotalSWOP;
                $userUnvoted = 0;
                $userUnvotedPeriod = 0;
            }

            $key = $poolAddress . $this->kPoolStruc;
            if( isset( $this->db[$key] ) )
            {
                $data = explode( '_', $this->db[$key] );
                $poolVoteSWOP = (int)$data[0];
                $poolActiveSWOP = (int)$data[1];
                $poolPeriod = (int)$data[2];
            }
            else
            {
                $uPoolVoteSWOP = $this->getInteger( $poolAddress . $this->kPoolVoteSWOP, 0 );
                $poolVoteSWOP = $uPoolVoteSWOP;
                $poolActiveSWOP = $uPoolVoteSWOP;
                $poolPeriod = 0;
            }

            $key = $this->kTotalStruc;
            if( isset( $this->db[$key] ) )
            {
                $data = explode( '_', $this->db[$key] );
                $totalVoteSWOP = (int)$data[0];
                $totalActiveSWOP = (int)$data[1];
                $totalPeriod = (int)$data[2];
            }
            else
            {
                $uTotalVoteSWOP = $this->getInteger( $this->kTotalVoteSWOP, 0 );
                $totalVoteSWOP = $uTotalVoteSWOP;
                $totalActiveSWOP = $uTotalVoteSWOP;
                $totalPeriod = 0;
            }

            $poolVoteDiffSWOP = $userPoolVoteSWOPnew - $userPoolVoteSWOP;
            $userTotalVoteSWOPnew = $userTotalVoteSWOP + $poolVoteDiffSWOP;
        
            if( $userTotalVoteSWOPnew > $userSWOPinGovernance ) exit( "$userTotalVoteSWOPnew > $userSWOPinGovernance" );
            if( $userTotalVoteSWOPnew < 0 ) exit( "$userTotalVoteSWOPnew < 0" );
            if( $userPoolVoteSWOPnew < 0 ) exit( "$userPoolVoteSWOPnew < 0" );

            if( $userPoolVoteSWOPnew >= $userPoolVoteSWOP )
            {
                $coef = $this->votingCoef();

                if( $userPoolVotePeriod == $this->currPeriod )
                {
                    $userPoolActiveVoteSWOPnew = $userPoolActiveVoteSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                    $userPoolFreezeSWOPnew = $userPoolFreezeSWOP;
                }
                else
                {
                    $userPoolActiveVoteSWOPnew = $userPoolVoteSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                    $userPoolFreezeSWOPnew = $userPoolVoteSWOP;
                }

                if( $userUnvotedPeriod == $this->currPeriod )
                {
                    $userUnvotedNew = max( 0, $userUnvoted - $poolVoteDiffSWOP );
                }
                else
                {
                    $userUnvotedNew = 0;
                }

                $userUnvotedPeriodNew = $this->currPeriod;
                $userPoolStrucNew = $userPoolVoteSWOPnew . "_" . $userPoolActiveVoteSWOPnew . "_" . $this->currPeriod . "_" . $userPoolFreezeSWOPnew;
                $this->db[$caller . "_" . $poolAddress . $this->kUserPoolStruc] = $userPoolStrucNew;

                $userTotalStrucNew = $userTotalVoteSWOPnew . "_" . $userUnvotedNew . "_" . $userUnvotedPeriodNew;
                $this->db[$caller . $this->kUserTotalStruc] = $userTotalStrucNew;

                $poolVoteSWOPnew = $poolVoteSWOP + $poolVoteDiffSWOP;
                if( $poolPeriod == $this->currPeriod )
                {
                    $poolActiveSWOPnew = $poolActiveSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                }
                else
                {
                    $poolActiveSWOPnew = $poolVoteSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                }
                $poolStrucNew = $poolVoteSWOPnew . "_" . $poolActiveSWOPnew . "_" . $this->currPeriod;
                $this->db[$poolAddress . $this->kPoolStruc] = $poolStrucNew;

                $totalVoteSWOPnew = $totalVoteSWOP + $poolVoteDiffSWOP;
                if( $totalPeriod == $this->currPeriod )
                {
                    $totalActiveSWOPnew = $totalActiveSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                }
                else
                {
                    $totalActiveSWOPnew = $totalVoteSWOP + fraction( $poolVoteDiffSWOP, $coef, 100000000 );
                }
                $totalStrucNew = $totalVoteSWOPnew . "_" . $totalActiveSWOPnew . "_" . $this->currPeriod;
                $this->db[$this->kTotalStruc] = $totalStrucNew;

                unset( $this->db[$caller . "_" . $poolAddress . $this->kUserPoolVoteSWOP] );
                unset( $this->db[$caller . $this->kUserTotalVoteSWOP] );
                unset( $this->db[$poolAddress . $this->kPoolVoteSWOP] );
                unset( $this->db[$this->kTotalVoteSWOP] );
            }
            else
            {
                $removePoolVote = -$poolVoteDiffSWOP;

                $userPoolVotePeriod_is_currPeriod = $userPoolVotePeriod == $this->currPeriod;

                $userPoolFreezeSWOPnew = $userPoolVotePeriod_is_currPeriod ? $userPoolFreezeSWOP : $userPoolVoteSWOP;
                $userPoolFreezeSWOP2 = min( $userPoolFreezeSWOP, $userPoolVoteSWOP );
                $userPoolFreezeSWOPnew2 = min( $userPoolFreezeSWOPnew, $userPoolVoteSWOPnew );
                if( $userPoolVoteSWOP - $userPoolFreezeSWOP === 0 )
                    $userPoolActiveVoteSWOPnew = $userPoolFreezeSWOPnew2;
                else
                    $userPoolActiveVoteSWOPnew = $userPoolFreezeSWOPnew2 + fraction( $userPoolActiveVoteSWOP - $userPoolFreezeSWOP, $userPoolVoteSWOPnew - $userPoolFreezeSWOPnew2, $userPoolVoteSWOP - $userPoolFreezeSWOP );
                $userPoolActiveVoteDiff = $userPoolActiveVoteSWOPnew - ( $userPoolVotePeriod_is_currPeriod ? $userPoolActiveVoteSWOP : $userPoolVoteSWOP );
                $newUnvoted = max( 0, $removePoolVote - ( $userPoolVotePeriod_is_currPeriod ? ( $userPoolVoteSWOP - $userPoolFreezeSWOP2 ) : 0 ) );
                $userUnvotedNew = $newUnvoted + ( $userUnvotedPeriod === $this->currPeriod ? $userUnvoted : 0 );
                $userUnvotedPeriodNew = $newUnvoted > 0 ? $this->currPeriod : $userUnvotedPeriod;

                $userPoolStrucNew = $userPoolVoteSWOPnew . "_" . $userPoolActiveVoteSWOPnew . "_" . $this->currPeriod . "_" . $userPoolFreezeSWOPnew;
                $this->db[$caller . "_" . $poolAddress . $this->kUserPoolStruc] = $userPoolStrucNew;

                $userTotalStrucNew = $userTotalVoteSWOPnew . "_" . $userUnvotedNew . "_" . $userUnvotedPeriodNew;
                $this->db[$caller . $this->kUserTotalStruc] = $userTotalStrucNew;

                $poolVoteSWOPnew = $poolVoteSWOP - $removePoolVote;
                if( $poolPeriod === $this->currPeriod )
                    $poolActiveSWOPnew = $poolActiveSWOP + $userPoolActiveVoteDiff;
                else
                    $poolActiveSWOPnew = $poolVoteSWOP + $userPoolActiveVoteDiff;
                $poolStrucNew = $poolVoteSWOPnew . "_" . $poolActiveSWOPnew . "_" . $this->currPeriod;
                $this->db[$poolAddress . $this->kPoolStruc] = $poolStrucNew;

                $totalVoteSWOPnew = $totalVoteSWOP - $removePoolVote;
                if( $totalPeriod === $this->currPeriod )
                    $totalActiveSWOPnew = $totalActiveSWOP + $userPoolActiveVoteDiff;
                else
                    $totalActiveSWOPnew = $totalVoteSWOP + $userPoolActiveVoteDiff;
                
                $totalStrucNew = $totalVoteSWOPnew . "_" . $totalActiveSWOPnew . "_" . $this->currPeriod;
                $this->db[$this->kTotalStruc] = $totalStrucNew;
            }

            unset( $this->db[$caller . "_" . $poolAddress . $this->kUserPoolVoteSWOP] );
            unset( $this->db[$caller . $this->kUserTotalVoteSWOP] );
            unset( $this->db[$poolAddress . $this->kPoolVoteSWOP] );
            unset( $this->db[$this->kTotalVoteSWOP] );
        }
        
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
    }
}

function dApp()
{
    static $d;

    if( !isset( $d ) )
    {
        global $dApp;
        $d = new dApp_Voting( $dApp );
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
                'votePoolWeight' => function( $tx ){ dApp()->votePoolWeight( $tx ); },
            ],

            govAddr()->address() =>
            [
                '*' => function( $tx ){ govAddr()->invoke( $tx ); },
            ],
        ],

        12 => 
        [
            govAddr()->address() => function( $tx ){ govAddr()->data( $tx ); },
            dApp()->address() => function( $tx ){ dApp()->data( $tx ); },
        ],

        13 => 
        [
            dApp()->address() => function( $tx ){ dApp()->contract( $tx ); },
        ],
    ];
}

$wk->log( 'finish' );
