<?php

require __DIR__ . '/common.php';

dAppReproduce( $wk, [ getTxs( $wk, dApp()->address() ) ], getFunctions() );

class dApp_Pools
{
    function address()
    {
        return '3PEbqViERCoKnmcSULh6n2aiMvUdSQdCsom';
    }

    function __construct( $dApp )
    {
        $this->pools = [];
    }

    function addPool( $tx )
    {
        global $wk;
        global $height;

        $poolAddress = $tx['call']['args'][0]['value'];
        $poolName = $tx['call']['args'][1]['value'];

        $myindex = count( $this->pools );
        $this->pools[$poolAddress] = $poolName;
        $myPools = implode( ',', array_keys( $this->pools ) );

        $indexRecord = $tx['stateChanges']['data'][0]['value'];
        $poolKey = $tx['stateChanges']['data'][1]['key'];
        $poolValue = $tx['stateChanges']['data'][1]['value'];
        $poolsRecord = $tx['stateChanges']['data'][2]['value'];

        if( $indexRecord !== $myindex )
            $wk->log( 'e', "$indexRecord !== $myindex" );
        if( $poolKey !== 'pool_' . $poolAddress || $poolValue !== $poolName )
            $wk->log( 'e', "$poolKey !== 'pool_' . $poolAddress || $poolValue !== $poolName" );
        if( $poolsRecord !== $myPools )
            $wk->log( 'e', "$poolsRecord !== $myPools" );
    }

    function renamePool( $tx )
    {
        global $wk;
        global $height;

        $wk->log( 'w', 'renamePool at ' . $tx['id'] );
    }
}

function dApp()
{
    static $d;

    if( !isset( $d ) )
    {
        global $dApp;
        $d = new dApp_Pools( $dApp );
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
                'addPool' => function( $tx ){ dApp()->addPool( $tx ); },
                'renamePool' => function( $tx ){ dApp()->renamePool( $tx ); },
            ],
        ],
    ];
}

$wk->log( 'finish' );
