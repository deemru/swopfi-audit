<?php

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, dApp()->address() ) ], getFunctions() );

class dApp_Governance_LP
{
    function address()
    {
        return '3P6J84oH51DzY6xk2mT5TheXRbrCwBMxonp';
    }

    function __construct( $dApp )
    {
        $this->$dApp = $dApp;
        $this->lastAirDropHeight = 0;
        $this->lastUpdateWeights = 0;
        $this->lastUpdatePoolInterest = 0;

        $this->totalAirDropped = 0;
        $this->db = [];
        $this->plusdb = [];
        $this->minusdb = [];

        $this->diff = 0;
        $this->sswop = 0;
        $this->ndiff = 0;
    }

    function incoming( $tx )
    {
        foreach( $tx['stateChanges']['transfers'] as $r )
        {
            $address = $r['address'];
            if( $address === $this->address() )
            {
                $amount = $r['amount'];
                $asset = isset( $r['asset'] ) ? $r['asset'] : 'WAVES';;
                $this->db[$asset] = $amount + ( isset( $this->db[$asset] ) ? $this->db[$asset] : 0 );
                $this->plusdb[$asset] = $amount + ( isset( $this->plusdb[$asset] ) ? $this->plusdb[$asset] : 0 );
            }
        }
    }

    function invoke( $tx )
    {
        global $wk;

        $sender = $tx['sender'];

        if( $sender !== $this->address() )
            return $this->incoming( $tx );

        $function = $tx['call']['function'];

        if( $function === 'exchange' )
        {
            $payment = $tx['payment'][0];
            $amount = $payment['amount'];
            $asset = isset( $payment['assetId'] ) ? $payment['assetId'] : 'WAVES';
            $this->db[$asset] = ( isset( $this->db[$asset] ) ? $this->db[$asset] : 0 ) - $amount;
            $this->minusdb[$asset] = ( isset( $this->minusdb[$asset] ) ? $this->minusdb[$asset] : 0 ) + $amount;
            $this->incoming( $tx );
        }
        else
        if( $function === 'airDrop' )
        {
            $payment = $tx['payment'][0];
            $amount = $payment['amount'];
            $asset = $payment['assetId'];
            if( $payment['assetId'] !== 'Ehie5xYpeN8op1Cctc6aGUrqx8jq3jtf1DSjXDbfm7aT' )
                exit( '$payment[assetId] !== ehie' );
            
            $diff = $tx['height'] - $this->lastAirDropHeight;

            if( $this->ndiff > 0 )
            {
                $this->diff = ( $this->diff * $this->ndiff + $diff ) / ( $this->ndiff + 1 );
                $this->sswop = ( $this->sswop * $this->ndiff + $amount ) / ( $this->ndiff + 1 );
            }
            ++$this->ndiff;

            //$wk->log( 'airDrop = ' . amount( $amount, 8 ) . ' SWOP (' . $tx['height'] . ':' . $diff . ')' );
            $this->lastAirDropHeight = $tx['height'];
            $this->totalAirDropped += $amount;

            $this->db[$asset] = ( isset( $this->db[$asset] ) ? $this->db[$asset] : 0 ) - $amount;
            $this->minusdb[$asset] = ( isset( $this->minusdb[$asset] ) ? $this->minusdb[$asset] : 0 ) + $amount;
        }
        else
        if( $function === 'updateWeights' )
        {
            $diff = $tx['height'] - $this->lastUpdateWeights;
            $wk->log( 'updateWeights (' . $tx['height'] . ':' . $diff . ')' );
            $this->lastUpdateWeights = $tx['height'];
        }
        else
        if( $function === 'updatePoolInterest' )
        {
            $diff = $tx['height'] - $this->lastUpdatePoolInterest;
            $wk->log( 'updatePoolInterest (' . $tx['height'] . ':' . $diff . ')' );
            $this->lastUpdatePoolInterest = $tx['height'];
        }
        else
            exit( $wk->log( 'e', 'unknown call at ' . $tx['id'] ) );
    }

    function absorb( $tx )
    {
        $this->lastid = $tx['id'];
        $sender = $tx['sender'];
        if( $sender === $this->address() )
        {
            $amount = $tx['fee'];
            $asset = isset( $payment['feeAssetId'] ) ? $payment['feeAssetId'] : 'WAVES';
            $this->db[$asset] = ( isset( $this->db[$asset] ) ? $this->db[$asset] : 0 ) - $amount;
            $this->minusdb[$asset] = ( isset( $this->minusdb[$asset] ) ? $this->minusdb[$asset] : 0 ) + $amount;
        }

        if( $tx['type'] === 16 )
            return $this->invoke( $tx );
    }
}

function dApp()
{
    static $d;

    if( !isset( $d ) )
    {
        global $dApp;
        $d = new dApp_Governance_LP( $dApp );
    }

    return $d;
}

function getFunctions()
{
    return
    [
        '*' => function( $tx )
        {
            dApp()->absorb( $tx );
        },
    ];
}

$wk->log( 'total plus:' );
foreach( dApp()->plusdb as $asset => $amount )
{
    $wk->log( '|- ' . amount( $amount, getDecimals( $asset ) ) . ' ' . getName( $asset ) );
}
$wk->log( 'total minus:' );
foreach( dApp()->minusdb as $asset => $amount )
{
    $wk->log( '|- ' . amount( $amount, getDecimals( $asset ) ) . ' ' . getName( $asset ) );
}
$wk->log( 'final balance:' );
foreach( dApp()->db as $asset => $amount )
{
    $wk->log( '|- ' . amount( $amount, getDecimals( $asset ) ) . ' ' . getName( $asset ) );
}
$wk->log( 'totalAirDropped = ' . amount( dApp()->totalAirDropped, 8 ) . ' SWOP' );

$wk->log( 'mean airDrop = ' . amount( dApp()->sswop, 8 ) . ' SWOP' );
$wk->log( 'mean period = ' . sprintf( '%.02f', dApp()->diff ) . ' blocks' );

$wk->log( dApp()->lastid );

$wk->log( 'done' );
