<?php

require __DIR__ . '/common.php';

$kdb = [];
foreach( $contracts as $rec )
{
    $meaning = $rec[0];
    $address = $rec[1];
    $wk->log( $address );
    $script = getDecompiledScript( getLastScript( $address ) );
    $keys = parsePublicKeys( $script );
    $wk->log( '---' );
    foreach( $keys as $key )
        $kdb[$key] = 1 + ( isset( $kdb[$key] ) ? $kdb[$key] : 0 );
}

$n = 0;
foreach( $kdb as $pubkey => $count )
{
    if( !isKeyUsed( $pubkey ) )
    {
        $wk->log( 's', $pubkey . ' (' . $count . ') => ' . $wk->getAddress() . ' - no usage in mainnet' );
    }
    else
    {
        $wk->log( 'w', $pubkey . ' (' . $count . ') => ' . $wk->getAddress() . ' - found usage in mainnet' );
        ++$n;
    }
}

$wk->log( '---' );
$wk->log( $n ? 'w' : 's', $n . ' out of ' . count( $kdb ) . ' public keys have out of scope usage in mainnet' );

function getSenderUsage( $address )
{
    $wk = new deemru\WavesKit;
    $wk->setNodeAddress( 'https://api.wavesplatform.com' );
    $txs = $wk->fetch( sprintf( '/v0/transactions/all?sender=%s&sort=desc&limit=1', $address ) );
    if( $txs === false || false === ( $txs = $wk->json_decode( $txs ) ) )
        exit( $wk->log( 'e', 'getSenderUsage( '. $address .' ) failed' ) );
    if( isset( $txs['data'][0]['data']['sender'] ) && $txs['data'][0]['data']['sender'] === $address )
        return true;
    return false;
}

function isAsset( $id )
{
    global $wk;
    $info = $wk->fetch( '/assets/details?id=' . $id );
    $info = $wk->json_decode( $info );
    return isset( $info[0]['assetId'] );
}

function parsePublicKeys( $script )
{
    global $wk;
    $prolog = "base58'";
    $epilog = "'";
    $offset = 0;

    $keys = [];

    $n = 0;
    for( ;; )
    {
        $start = strpos( $script, $prolog, $offset );
        if( $start === false )
            break;
        $offset = $start + strlen( $prolog );
        $end = strpos( $script, $epilog, $offset );
        if( $end === false )
            exit( $wk->log( 'e', 'parsePublicKeys() failed' ) );
        $key = substr( $script, $offset, $end - $offset );
        if( strlen( $wk->base58Decode( $key ) ) === 32 && !isAsset( $key ) )
        {
            $keys[] = $key;
            $wk->log( '|-' . ++$n . ') '. $key );
        }
        //else
            //$wk->log( 'skip - ' . $key );
    }

    return $keys;
}

function isKeyUsed( $pubkey )
{
    global $wk;
    $wk->setPublicKey( $pubkey );
    return getSenderUsage( $wk->getAddress() );
}
