<?php

define( 'WFRACTION', true );
define( 'scaleValue8', 100000000 );
define( 'scaleValue3', 1000 );
define( 'slippageToleranceDelimiter', 1000 );
define( 'scaleValue8Digits', 8 );
define( 'scaleValue12', 1000000000000 );
define( 'scaleValue12Digits', 12 );
define( 'dAppThreshold', 50 );
define( 'dAppThresholdDelimiter', 100 );
define( 'ratioThresholdMax', 100000000 );
define( 'ratioThresholdMin', 99999000 );
define( 'exchangeRatioLimitMin', 90000000 );
define( 'exchangeRatioLimitMax', 110000000 );

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, flat()->address() ) ], getFunctions() );

function getAssetInfo( $assetId )
{
    if( !isset( $assetId ) )
        return [ "WAVES", "WAVES", 8 ];
    return [ $assetId, getName( $assetId ), getDecimals( $assetId ) ];
}

class dApp_FLAT
{
    function address()
    {
        return '3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF';
    }

    function __construct()
    {
        $this->version = "2.0.0";
        $this->keyVersion = "version";
        $this->keyActive = "active";
        $this->keyAssetIdA = "A_asset_id";
        $this->keyAssetIdB = "B_asset_id";
        $this->kBalanceA = "A_asset_balance";
        $this->kBalanceB = "B_asset_balance";
        $this->kShareAssetId = "share_asset_id";
        $this->kShareAssetSupply = "share_asset_supply";
        $this->kFee = "commission";
        $this->kFeeScaleDelimiter = "commission_scale_delimiter";
        $this->kInvariant = "invariant";
        $this->keyCause = "shutdown_cause";

        $this->fee = 500;
        $this->feeGovernance = 200;
        $this->feeScaleDelimiter = 1000000;
        $this->invariant = 0;

        $this->fee1 = 0;
        $this->fee2 = 0;
        $this->feesum = 0;
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

    function deductStakingFee( $amount, $assetId )
    {
        global $height;
        if( $height < 2344881 )
            return $amount;

        if( $assetId == 'DG2xFkPdDwKUoBkzGAhQtLpSGzfXLiCYPEzeKH2Ad24p' )
            return $amount - ( 9 * 30000 );
        return $amount;
    }

    function skewness( $x, $y )
    {
        return intdiv( intdiv( fraction( 1000000000000, $x, $y ) + fraction( 1000000000000, $y, $x ), 2 ), 10000 );
    }

    function invariantCalc( $x, $y )
    {
        $sk = $this->skewness( $x, $y );
        $result = fraction( $x + $y, 100000000, poweroot( $sk, 8, 50, 2, 8, true ) )
                + 2 * fraction( poweroot( fraction( $x, $y, 100000000 ), 0, 5, 1, 4, false ),
                                poweroot( $sk - 46000000, 8, 50, 2, 8, false ), 100000000 );
        return $result;
    }

    function calculateHowManySendA( $amountToSendEstimated, $minTokenReceiveAmount, $amountA, $amountB, $tokenReceiveAmount )
    {
        $slippageValue = scaleValue8 - intdiv( scaleValue8, 10000000 );
        $deltaMaxAndMinSendValue = $amountToSendEstimated - $minTokenReceiveAmount;
    
        $amountToSendStep1 = $amountToSendEstimated - intdiv( 1 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep2 = $amountToSendEstimated - intdiv( 2 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep3 = $amountToSendEstimated - intdiv( 3 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep4 = $amountToSendEstimated - intdiv( 4 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep5 = $amountToSendEstimated - intdiv( 5 * $deltaMaxAndMinSendValue, 5 );
   
        $y = $amountB + $tokenReceiveAmount;
        $invariantNew = $this->invariantCalc( $amountA - $amountToSendEstimated, $y );
        $invariantEstimatedRatio = fraction( $this->invariant, scaleValue8, $invariantNew );
    
        if( $invariantEstimatedRatio > $slippageValue && $invariantNew - $this->invariant > 0 )
        {
            ++$this->fee1;
            return $amountToSendEstimated;
        }
        else if( $this->invariantCalc( $amountA - $amountToSendStep1, $y ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep1 - intdiv( $amountToSendStep1 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep1 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $amountA - $amountToSendStep2, $y ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep2 - intdiv( $amountToSendStep2 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep2 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $amountA - $amountToSendStep3, $y ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep3 - intdiv( $amountToSendStep3 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep3 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $amountA - $amountToSendStep4, $y ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep4 - intdiv( $amountToSendStep4 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep4 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $amountA - $amountToSendStep5, $y ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep5 - intdiv( $amountToSendStep5 * ( $this->feeScaleDelimiter - $this->fee ) , $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep5 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else
            exit( 'err2' );
    }

    function calculateHowManySendB( $amountToSendEstimated, $minTokenReceiveAmount, $amountA, $amountB, $tokenReceiveAmount )
    {
        $slippageValue = scaleValue8 - intdiv( scaleValue8, 10000000 );
        $deltaMaxAndMinSendValue = $amountToSendEstimated - $minTokenReceiveAmount;

        $amountToSendStep1 = $amountToSendEstimated - intdiv( 1 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep2 = $amountToSendEstimated - intdiv( 2 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep3 = $amountToSendEstimated - intdiv( 3 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep4 = $amountToSendEstimated - intdiv( 4 * $deltaMaxAndMinSendValue, 5 );
        $amountToSendStep5 = $amountToSendEstimated - intdiv( 5 * $deltaMaxAndMinSendValue, 5 );
   
        $x = $amountA + $tokenReceiveAmount;
        $invariantNew = $this->invariantCalc( $x, $amountB - $amountToSendEstimated );
        $invariantEstimatedRatio = fraction( $this->invariant, scaleValue8, $invariantNew );
    
        if( $invariantEstimatedRatio > $slippageValue && $invariantNew - $this->invariant > 0 )
        {
            ++$this->fee1;
            return $amountToSendEstimated;
        }
        else if( $this->invariantCalc( $x, $amountB - $amountToSendStep1 ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep1 - intdiv( $amountToSendStep1 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep1 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $x, $amountB - $amountToSendStep2 ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep2 - intdiv( $amountToSendStep2 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep2 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $x, $amountB - $amountToSendStep3 ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep3 - intdiv( $amountToSendStep3 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep3 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $x, $amountB - $amountToSendStep4 ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep4 - intdiv( $amountToSendStep4 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep4 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else if( $this->invariantCalc( $x, $amountB - $amountToSendStep5 ) - $this->invariant > 0 )
        {
            ++$this->fee2;
            $this->feesum += $amountToSendStep5 - intdiv( $amountToSendStep5 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
            return intdiv( $amountToSendStep5 * ( $this->feeScaleDelimiter - $this->fee ), $this->feeScaleDelimiter );
        }
        else
            echo( 'err2' );
    }

    function data( $tx )
    {
        absorber( $this, $tx['data'] );
        $this->balanceA = $this->db[$this->kBalanceA];
        $this->balanceB = $this->db[$this->kBalanceB];
        $this->invariant = $this->db[$this->kInvariant];
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
            $invariantCalculated = (int)$this->invariantCalc( $pmtAmountA, $pmtAmountB );
            {
                $this->Entry( $this->keyVersion, $this->version);
                $this->Entry( $this->keyActive, true );
                $this->Entry( $this->keyAssetIdA, $pmtStrAssetIdA);
                $this->Entry( $this->keyAssetIdB, $pmtStrAssetIdB);
                $this->Entry( $this->kBalanceA, $pmtAmountA);
                $this->Entry( $this->kBalanceB, $pmtAmountB);
                $this->Entry( $this->kFee, $this->fee );
                $this->Entry( $this->kFeeScaleDelimiter, $this->feeScaleDelimiter);
                $this->Entry( $this->kShareAssetId, $tx['stateChanges']['data'][9]['value'] );
                $this->Entry( $this->kShareAssetSupply, (int)$shareInitialSupply );
                $this->Entry( $this->kInvariant, $invariantCalculated );

                $this->assetIdA = $pmtStrAssetIdA;
                $this->assetIdB = $pmtStrAssetIdB;
                $this->balanceA = $pmtAmountA;
                $this->balanceB = $pmtAmountB;
                $this->shareAssetSupply = $shareInitialSupply;
                $this->invariant = $invariantCalculated;

                $this->totalOutA = 0;
                $this->totalOutB = 0;
            }
        }
        else
        if( $function === 'exchange' )
        {
            if( $tx['id'] === 'BuyqByPTMGXVk76KXoFgG2B2JxoMNfX9UPPox4FrWhKy' )
                $this->debug = true;

            $estimatedAmountToReceive = $tx['call']['args'][0]['value'];
            $minAmountToReceive = $tx['call']['args'][1]['value'];
            list( $pmtAmount, $pmtAssetId ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];

            if( $pmtAssetId == $this->assetIdA )
            {
                $assetIdSend = $this->assetIdB;
                $amountWithoutFee = $this->calculateHowManySendB( $estimatedAmountToReceive, $minAmountToReceive, $this->balanceA, $this->balanceB, $pmtAmount );
                $amountWithFee = fraction( $amountWithoutFee, $this->feeScaleDelimiter - $this->fee, $this->feeScaleDelimiter );
                $governanceReward = fraction( $amountWithoutFee, $this->feeGovernance, $this->feeScaleDelimiter );

                $this->feesum += $amountWithoutFee - $amountWithFee;

                $newBalanceA = $this->balanceA + $pmtAmount;
                $newBalanceB = $this->balanceB - $amountWithFee - $governanceReward;
                {
                    $this->Entry( $this->kBalanceA, $newBalanceA );
                    $this->Entry( $this->kBalanceB, $newBalanceB );
                    $this->Entry( $this->kInvariant, $this->invariantCalc( $newBalanceA, $newBalanceB ) );
                }
            }
            else
            {
                $assetIdSend = $this->assetIdA;
                $amountWithoutFee = $this->calculateHowManySendA( $estimatedAmountToReceive, $minAmountToReceive, $this->balanceA, $this->balanceB, $pmtAmount );
                $amountWithFee = fraction( $amountWithoutFee, $this->feeScaleDelimiter - $this->fee, $this->feeScaleDelimiter );
                $governanceReward = fraction( $amountWithoutFee, $this->feeGovernance, $this->feeScaleDelimiter );

                $this->feesum += $amountWithoutFee - $amountWithFee;

                $newBalanceA = $this->balanceA - $amountWithFee - $governanceReward;
                $newBalanceB = $this->balanceB + $pmtAmount;
                {
                    $this->Entry( $this->kBalanceA, $newBalanceA );
                    $this->Entry( $this->kBalanceB, $newBalanceB );
                    $this->Entry( $this->kInvariant, $this->invariantCalc( $newBalanceA, $newBalanceB ) );
                }
            }

            $this->balanceA = $newBalanceA;
            $this->balanceB = $newBalanceB;
            $this->invariant = $this->db[$this->kInvariant];
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

            $invariantCalculated = $this->invariantCalc( $this->balanceA + $pmtAmountA, $this->balanceB + $pmtAmountB );

            {
                $this->Entry( $this->kBalanceA, $this->balanceA + $pmtAmountA );
                $this->Entry( $this->kBalanceB, $this->balanceB + $pmtAmountB );
                $this->Entry( $this->kShareAssetSupply, $this->shareAssetSupply + $shareTokenToPayAmount );
                $this->Entry( $this->kInvariant, $invariantCalculated );

                $this->shareAssetSupply += $shareTokenToPayAmount;
                $this->balanceA += $pmtAmountA;
                $this->balanceB += $pmtAmountB;
                $this->invariant = $this->db[$this->kInvariant];
            }
        }
        else
        if( $function === 'replenishWithOneToken' )
        {
            $virtualSwapTokenPay = $tx['call']['args'][0]['value'];
            $virtualSwapTokenGet = $tx['call']['args'][1]['value'];
            list( $pmtAmount, $pmtAssetId ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];

            if( $pmtAssetId == $this->assetIdA )
            {
                $virtReplA = $pmtAmount - $virtualSwapTokenPay;
                $virtReplB = $virtualSwapTokenGet;
                $balanceAfterSwapA = $this->balanceA + $virtualSwapTokenPay;
                $balanceAfterSwapB = $this->balanceB - $virtualSwapTokenGet;
        
                $ratioShareTokensInA = fraction( $this->deductStakingFee( $virtReplA, $this->assetIdA ), scaleValue8, $balanceAfterSwapA );
                $ratioShareTokensInB = fraction( $this->deductStakingFee( $virtReplB, $this->assetIdB ), scaleValue8, $balanceAfterSwapB );

                $shareTokenToPayAmount = fraction( min( $ratioShareTokensInA, $ratioShareTokensInB ), $this->shareAssetSupply, scaleValue8 );
                $invariantCalculated = $this->invariantCalc( $this->balanceA + $pmtAmount, $this->balanceB );
        
                $newBalanceA = $this->balanceA + $pmtAmount;
                $newBalanceB = $this->balanceB;
                {
                    $this->Entry( $this->kShareAssetSupply, $this->shareAssetSupply + $shareTokenToPayAmount );
                    $this->Entry( $this->kBalanceA, $newBalanceA );
                    $this->Entry( $this->kInvariant, $invariantCalculated );
                }
            }
            else
            {
                $virtReplB = $pmtAmount - $virtualSwapTokenPay;
                $virtReplA = $virtualSwapTokenGet;
                $balanceAfterSwapA = $this->balanceA - $virtualSwapTokenGet;
                $balanceAfterSwapB = $this->balanceB + $virtualSwapTokenPay;
        
                $ratioShareTokensInA = fraction( $this->deductStakingFee( $virtReplA, $this->assetIdA ), scaleValue8, $balanceAfterSwapA );
                $ratioShareTokensInB = fraction( $this->deductStakingFee( $virtReplB, $this->assetIdB ), scaleValue8, $balanceAfterSwapB );

                $shareTokenToPayAmount = fraction( min( $ratioShareTokensInA, $ratioShareTokensInB ), $this->shareAssetSupply, scaleValue8 );
                $invariantCalculated = $this->invariantCalc( $this->balanceA, $this->balanceB + $pmtAmount);
        
                $newBalanceA = $this->balanceA;
                $newBalanceB = $this->balanceB + $pmtAmount;
                {
                    $this->Entry( $this->kShareAssetSupply, $this->shareAssetSupply + $shareTokenToPayAmount );
                    $this->Entry( $this->kBalanceB, $newBalanceB );
                    $this->Entry( $this->kInvariant, $invariantCalculated );
                }
            }

            $this->shareAssetSupply += $shareTokenToPayAmount;
            $this->balanceA = $newBalanceA;
            $this->balanceB = $newBalanceB;
            $this->invariant = $this->db[$this->kInvariant];
        }
        else
        if( $function === 'withdraw' )
        {
            list( $pmtAmount, $pmtAssetId ) = [ $tx['payment'][0]['amount'], $tx['payment'][0]['assetId'] ];

            $amountToPayA = $this->deductStakingFee( fraction( $pmtAmount, $this->balanceA, $this->shareAssetSupply ), $this->assetIdA );
            $amountToPayB = $this->deductStakingFee( fraction( $pmtAmount, $this->balanceB, $this->shareAssetSupply ), $this->assetIdB );


            $invariantCalculated = $this->invariantCalc( $this->balanceA - $amountToPayA, $this->balanceB - $amountToPayB );

            {
                $this->Entry( $this->kBalanceA, $this->balanceA - $amountToPayA );
                $this->Entry( $this->kBalanceB, $this->balanceB - $amountToPayB );
                $this->Entry( $this->kShareAssetSupply, $this->shareAssetSupply - $pmtAmount );
                $this->Entry( $this->kInvariant, $invariantCalculated );

                $this->shareAssetSupply -= $pmtAmount;
                $this->balanceA -= $amountToPayA;
                $this->balanceB -= $amountToPayB;
                $this->invariant = $this->db[$this->kInvariant];
            }
        }
        else
        if( $function === 'takeIntoAccountExtraFunds' )
        {
            $this->leave = $tx['call']['args'][0]['value'];
            //$wk->log( "leave: $this->leave" );

            absorber( $this, $tx['stateChanges']['data'] );
            $this->shareAssetSupply = $this->db[$this->kShareAssetSupply];
            $this->balanceA = $this->db[$this->kBalanceA];
            $this->balanceB = $this->db[$this->kBalanceB];

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
        else if( $function === 'shutdown' )
        {
            absorber( $this, $tx['stateChanges']['data'] );
        }
        else if( $function === 'activate' )
        {
            absorber( $this, $tx['stateChanges']['data'] );
        }
        else
            exit( $function );

        if( WFRACTION && isset( $tx['stateChanges'] ) )
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

function flat()
{
    static $d;
    if( !isset( $d ) )
        $d = new dApp_FLAT;
    return $d;
}

function getFunctions()
{
    return
    [
        16 => 
        [
            flat()->address() =>
            [
                '*' => function( $tx ){ flat()->invoke( $tx ); },
            ],
        ],
        12 => 
        [
            flat()->address() => function( $tx ){ flat()->data( $tx ); },
        ],
    ];
}

$c = flat();
foreach( $c->db as $key => $value )
    $wk->log( "$key: = $value" );
$wk->log( "sdiff = {$c->sdiff}" );
$wk->log( "fee1 = {$c->fee1}" );
$wk->log( "fee2 = {$c->fee2}" );
$wk->log( "feesum = {$c->feesum}" );
$wk->log( 'finish' );
