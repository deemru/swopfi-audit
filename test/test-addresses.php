<?php

require __DIR__ . '/common.php';

$adb = [];
foreach( $contracts as $rec )
{
    $meaning = $rec[0];
    $address = $rec[1];
    $wk->log( $address );
    $script = getDecompiledScript( getLastScript( $address ) );
    $addresses = parseAddresses( $script );
    $wk->log( '---' );
    foreach( $addresses as $address )
        $adb[$address] = 1 + ( isset( $adb[$address] ) ? $adb[$address] : 0 );
}

$n = 0;
foreach( $adb as $address => $count )
{
    $wk->log( 's', $address . ' (' . $count . ')' );
}

function parseAddresses( $script )
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
            exit( $wk->log( 'e', 'parseAddresses() failed' ) );
        $key = substr( $script, $offset, $end - $offset );
        if( strlen( $key ) === 35 )
        {
            $keys[] = $key;
            $wk->log( '|-' . ++$n . ') '. $key );
        }
    }

    return $keys;
}
