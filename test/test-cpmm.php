<?php

define( 'WFRACTION', true );
define( 'scaleValue8', 100000000 );
define( 'scaleValue3', 1000 );
define( 'slippageToleranceDelimiter', 1000 );
define( 'scaleValue8Digits', 8 );

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, cpmm()->address() ) ], getFunctions() );

function getAssetInfo( $assetId )
{
    if( !isset( $assetId ) )
        return [ "WAVES", "WAVES", 8 ];
    return [ $assetId, getName( $assetId ), getDecimals( $assetId ) ];
}

class dApp_CPMM
{
    function address()
    {
        return '3PMDFxmG9uXAbuQgiNogZCBQASvCHt1Mdar';
    }

    function __construct()
    {
        $this->version = "1.0.0";
        $this->keyVersion = "version";
        $this->keyActive = "active";
        $this->keyAssetIdA = "A_asset_id";
        $this->keyAssetIdB = "B_asset_id";
        $this->keyBalanceA = "A_asset_balance";
        $this->keyBalanceB = "B_asset_balance";
        $this->keyShareAssetId = "share_asset_id";
        $this->keyShareAssetSupply = "share_asset_supply";
        $this->keyCommission = "commission";
        $this->keyCommissionScaleDelimiter = "commission_scale_delimiter";
        $this->keyCause = "shutdown_cause";

        $this->commission = 3000;
        $this->commissionGovernance = 1200;
        $this->commissionScaleDelimiter = 1000000;
    }

    function getInteger( $key, $default = null )
    {
        if( !isset( $this->db[$key] ) && isset( $default ) )
            return $default;
        return $this->db[$key];
    }

    function Entry( $key, $value )
    {
        $this->db[$key] = $value;
    }

    function calculateFees( $tokenFrom, $tokenTo, $pmtAmount )
    {
        $amountWithoutFee = fraction( $tokenTo, $pmtAmount, $pmtAmount + $tokenFrom );
        $amountWithFee = fraction( $amountWithoutFee, $this->commissionScaleDelimiter - $this->commission, $this->commissionScaleDelimiter );
        $governanceReward = fraction( $amountWithoutFee, $this->commissionGovernance, $this->commissionScaleDelimiter );
        return [ $amountWithoutFee, $amountWithFee, $governanceReward ];
    }

    function deductStakingFee( $amount, $assetId )
    {
        if( $assetId == 'DG2xFkPdDwKUoBkzGAhQtLpSGzfXLiCYPEzeKH2Ad24p' )
            return $amount - ( 9 * 30000 );
        return $amount;
    }

    function invoke( $tx )
    {
        global $wk;
        $function = $tx['call']['function'];

        if( $function === 'init' )
        {
            list( $pmtAmountA, $pmtAssetIdA ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];
            list( $pmtAmountB, $pmtAssetIdB ) = [ $tx['payment'][1]['amount'], $tx['payment'][1]['assetId'] ];
            list( $pmtStrAssetIdA, $pmtAssetNameA, $pmtDecimalsA ) = getAssetInfo( $pmtAssetIdA );
            list( $pmtStrAssetIdB, $pmtAssetNameB, $pmtDecimalsB ) = getAssetInfo( $pmtAssetIdB );

            $shareName = "s" . substr( $pmtAssetNameA, 0, 7 ) . "_" . substr( $pmtAssetNameB, 0, 7 );
            $shareDescription = "ShareToken of SwopFi protocol for " . $pmtAssetNameA . " and " . $pmtAssetNameB . " at address " . $this->address();

            $shareDecimals = intdiv( $pmtDecimalsA + $pmtDecimalsB , 2 );
            $shareInitialSupply = fraction( power( $pmtAmountA, $pmtDecimalsA, 5, 1, $pmtDecimalsA, 'HALFDOWN' ),
                                            power( $pmtAmountB, $pmtDecimalsB, 5, 1, $pmtDecimalsB, 'HALFDOWN' ),
                                            power( 10, 0, $shareDecimals, 0, 0, 'HALFDOWN' ) );
            $shareInitialSupply = (int)$shareInitialSupply;
            {
                $this->Entry( $this->keyVersion, $this->version);
                $this->Entry( $this->keyActive, true );
                $this->Entry( $this->keyAssetIdA, $pmtStrAssetIdA);
                $this->Entry( $this->keyAssetIdB, $pmtStrAssetIdB);
                $this->Entry( $this->keyBalanceA, $pmtAmountA);
                $this->Entry( $this->keyBalanceB, $pmtAmountB);
                $this->Entry( $this->keyCommission, $this->commission );
                $this->Entry( $this->keyCommissionScaleDelimiter, $this->commissionScaleDelimiter);
                $this->Entry( $this->keyShareAssetId, $tx['stateChanges']['data'][8]['value'] );
                $this->Entry( $this->keyShareAssetSupply, (int)$shareInitialSupply );

                $this->assetIdA = $pmtStrAssetIdA;
                $this->assetIdB = $pmtStrAssetIdB;
                $this->balanceA = $pmtAmountA;
                $this->balanceB = $pmtAmountB;
                $this->shareAssetSupply = $shareInitialSupply;

                $this->totalOutA = 0;
                $this->totalOutB = 0;
            }
        }
        else
        if( $function === 'exchange' )
        {
            list( $pmtAmount, $pmtAssetId ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];

            if( $pmtAssetId == $this->assetIdA )
            {
                $assetIdSend = $this->assetIdB;
                list( $amountWithoutFee, $amountWithFee, $governanceReward ) = $this->calculateFees( $this->balanceA, $this->balanceB, $pmtAmount );

                $newBalanceA = $this->balanceA + $pmtAmount;
                $newBalanceB = $this->balanceB - $amountWithFee - $governanceReward;

                $this->totalOutB += $amountWithFee + $governanceReward;

                {
                    $this->Entry( $this->keyBalanceA, $newBalanceA );
                    $this->Entry( $this->keyBalanceB, $newBalanceB );
                }
            }
            else
            {
                $assetIdSend = $this->assetIdA;
                list( $amountWithoutFee, $amountWithFee, $governanceReward ) = $this->calculateFees( $this->balanceB, $this->balanceA, $pmtAmount );

                $newBalanceA = $this->balanceA - $amountWithFee - $governanceReward;
                $newBalanceB = $this->balanceB + $pmtAmount;

                $this->totalOutA += $amountWithFee + $governanceReward;

                {
                    $this->Entry( $this->keyBalanceA, $newBalanceA );
                    $this->Entry( $this->keyBalanceB, $newBalanceB );
                }
            }

            $this->balanceA = $newBalanceA;
            $this->balanceB = $newBalanceB;
        }
        else
        if( $function === 'replenishWithTwoTokens' )
        {
            list( $pmtAmountA, $pmtAssetIdA ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];
            list( $pmtAmountB, $pmtAssetIdB ) = [ $tx['payment'][1]['amount'], $tx['payment'][1]['assetId'] ];

            $pmtAmountA = $this->deductStakingFee( $pmtAmountA, $pmtAssetIdA );
            $pmtAmountB = $this->deductStakingFee( $pmtAmountB, $pmtAssetIdB );

            $ratioShareTokensInA = fraction( $pmtAmountA, scaleValue8, $this->balanceA );
            $ratioShareTokensInB = fraction( $pmtAmountB, scaleValue8, $this->balanceB );
            $shareTokenToPayAmount = fraction( min( $ratioShareTokensInA, $ratioShareTokensInB ), $this->shareAssetSupply, scaleValue8 );

            {
                $this->Entry( $this->keyBalanceA, $this->balanceA + $pmtAmountA );
                $this->Entry( $this->keyBalanceB, $this->balanceB + $pmtAmountB );
                $this->Entry( $this->keyShareAssetSupply, $this->shareAssetSupply + $shareTokenToPayAmount );

                $this->shareAssetSupply += $shareTokenToPayAmount;
                $this->balanceA += $pmtAmountA;
                $this->balanceB += $pmtAmountB;
            }
        }
        else
        if( $function === 'withdraw' )
        {
            list( $pmtAmount, $pmtAssetId ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];

            $amountToPayA = $this->deductStakingFee( fraction( $pmtAmount, $this->balanceA, $this->shareAssetSupply ), $this->assetIdA );
            $amountToPayB = $this->deductStakingFee( fraction( $pmtAmount, $this->balanceB, $this->shareAssetSupply ), $this->assetIdB );

            {
                $this->Entry( $this->keyBalanceA, $this->balanceA - $amountToPayA );
                $this->Entry( $this->keyBalanceB, $this->balanceB - $amountToPayB );
                $this->Entry( $this->keyShareAssetSupply, $this->shareAssetSupply - $pmtAmount );

                $this->shareAssetSupply -= $pmtAmount;
                $this->balanceA -= $amountToPayA;
                $this->balanceB -= $amountToPayB;
            }
        }
        else
        if( $function === 'takeIntoAccountExtraFunds' )
        {
            $this->leave = $tx['call']['args'][0]['value'];
            //$wk->log( "leave: $this->leave" );

            absorber( $this, $tx['stateChanges']['data'] );
            $this->shareAssetSupply = $this->db[$this->keyShareAssetSupply];
            $this->balanceA = $this->db[$this->keyBalanceA];
            $this->balanceB = $this->db[$this->keyBalanceB];

            if( isset( $this->lastTake ) )
            {
                $diff = $tx['height'] - $this->lastTake;
                $this->sdiff = ( $this->sdiff * $this->takes + $diff ) / ( $this->takes + 1 );
                $this->lastTake = $tx['height'];
                ++$this->takes;
            }
            else
            {
                $this->lastTake = $tx['height'];
                $this->takes = 0;
                $this->sdiff = 0;
            }
        }
        else
            exit( $function );

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
    }
}

function cpmm()
{
    static $d;
    if( !isset( $d ) )
        $d = new dApp_CPMM;
    return $d;
}

function getFunctions()
{
    return
    [
        16 => 
        [
            cpmm()->address() =>
            [
                '*' => function( $tx ){ cpmm()->invoke( $tx ); },
            ],
        ],
    ];
}

$c = cpmm();
foreach( $c->db as $key => $value )
    $wk->log( "$key: = $value" );
$wk->log( "sdiff = {$c->sdiff}" );
$wk->log( 'finish' );
