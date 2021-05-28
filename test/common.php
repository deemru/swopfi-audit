<?php

define( 'WUPDATE', trim( getenv( 'WUPDATE' ) ) );
if( !defined( 'WFRACTION' ) ) define( 'WFRACTION', trim( getenv( 'WFRACTION' ) ) );
define( 'WFIX', true );
define( 'WK_CURL_TIMEOUT', 30 );
define( 'HISTAMP', 1620939467347 );
define( 'HIBLOCK', 2589057 );

require __DIR__ . '/vendor/autoload.php';
use deemru\WavesKit;
use deemru\Triples;

// global default $wk
$wk = new WavesKit;
$wk->setNodeAddress( ['http://127.0.0.1:6869', 'https://nodes.swop.fi'] );
$wk->curlSetBestOnError = 1;

$contracts = [
    ['pool-cpmm',       '3P8FVZgAJUAq32UEZtTw84qS4zLqEREiEiP'], // WAVES/BTC
    ['pool-cpmm',       '3PHaNgomBkrvEL2QnuJarQVJa71wjw9qiqG'], // WAVES/USDN
    ['pool-cpmm-eurn',  '3PK7Xe5BiedRyxHLuMQx5ey9riUQqvUths2'], // WAVES/EURN
    ['pool-cpmm',       '3P27S9V36kw2McjWRZ37AxTx8iwkd7HXw6W'], // SWOP/USDN
    ['pool-flat',       '3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1'], // USDT/USDN
    ['pool-flat',       '3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF'], // USDC/USDN
    ['pool-cpmm',       '3PDWi8hjQJjXhyTpeaiEYfFKWBd1iC4udfF'], // USDLP/USDN
    ['pool-cpmm',       '3PNr615DPhHpCJSq1atHYKKnoauWGHsYWBP'], // USDCLP/USDN
    ['pool-cpmm',       '3PACj2DLTw3uUhsUmT98zHU5M4hPufbHKav'], // BTC/USDN
    ['pool-cpmm-nsbt',  '3P2V63Xd6BviDkeMzxhUw2SJyojByRz8a8m'], // NSBT/USDN
    ['pool-cpmm',       '3PNEC4YKqZiMMytFrYRVtpW2ujvi3aGXRPm'], // ETH/USDN
    ['pool-cpmm',       '3P6DLdJTP2EySq9MFdJu6beUevrQd2sVVBh'], // WEST/USDN
    ['pool-cpmm',       '3PMDFxmG9uXAbuQgiNogZCBQASvCHt1Mdar'], // WCT/USDN
    ['pool-cpmm',       '3P9o2H6G5d2xXBTfBEwjzHc16RLSZLFLQjp'], // CRV/USDN
    ['pool-cpmm',       '3P4Ftyud3U3xnuR8sTc1RvV4iQD62TcKndy'], // SIGN/USDN
    ['pool-cpmm',       '3PKy2mZqnvT2EtpwDim9Mgs6YvCRe4s85nX'], // FL/USDN

    ['early-birds',     '3PJuspTjxHhEJQjMEmLM5upiGQYCtCi5LyD'],
    ['governance-lp',   '3P6J84oH51DzY6xk2mT5TheXRbrCwBMxonp'],
    ['governance',      '3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS'],
    ['farming',         '3P73HDkPqG15nLXevjCbmXtazHYTZbpPoPw'],
    ['voting',          '3PQZWxShKGRgBN1qoJw6B4s9YWS9FneZTPg'],
    ['pools',           '3PEbqViERCoKnmcSULh6n2aiMvUdSQdCsom'],
];

function fraction( $a, $b, $c )
{
    if( WFRACTION ) // blockchain integer precision
        return gmp_intval( gmp_div( gmp_mul( $a, $b ), $c ) );
    else // floating double precision
        return (double)$a * $b / $c;
}

function amount( $amount, $decimals )
{
    $amount = (int)$amount;

    $sign = '';
    if( $amount < 0 )
    {
        $sign = '-';
        $amount = -$amount;
    }
    $amount = (string)$amount;
    if( $decimals )
    {
        if( strlen( $amount ) <= $decimals )
            $amount = str_pad( $amount, $decimals + 1, '0', STR_PAD_LEFT );
        $amount = substr_replace( $amount, '.', -$decimals, 0 );
    }

    return $sign . $amount;
}

function txkey( $height, $index )
{
    return ( $height << 32 ) | $index;
}

function getTxKey( $wk, $tx )
{
    static $blocks = [];

    $height = $tx['height'];
    if( !isset( $blocks[$height] ) )
    {
        if( count( $blocks ) > 100 )
            $blocks = [];

        for( ;; )
        {
            $block = $wk->getBlockAt( $height );
            if( $block === false )
            {
                sleep( 1 );
                continue;
            }

            $blocks[$height] = $block;
            break;
        }
    }
    else
    {
        $block = $blocks[$height];
    }

    $index = 0;
    foreach( $block['transactions'] as $btx )
    {
        if( $tx['id'] === $btx['id'] )
            return txkey( $height, $index );
        ++$index;
    }

    exit( $wk->log( 'e', 'getTxKey() failed' ) );
}

function json_unpack( $data ){ return json_decode( gzinflate( $data ), true, 512, JSON_BIGINT_AS_STRING ); }
function json_pack( $data ){ return gzdeflate( json_encode( $data ), 9 ); }

function getTxs( WavesKit $wk, $address, $batch = 100 )
{
    $dbpath = __DIR__ . '/_' . $address . '.sqlite';
    $isFirst = !file_exists( $dbpath );
    $triples = new Triples( 'sqlite:' . $dbpath, 'ts', true, [ 'INTEGER PRIMARY KEY', 'TEXT UNIQUE', 'TEXT' ] );

    if( !$isFirst )
    {
        $isFirst = true;
        $q = $triples->query( 'SELECT r0 FROM ts ORDER BY r0 ASC' );
        foreach( $q as $r )
        {
            $q->closeCursor();
            $isFirst = false;
            break;
        }
    }    

    $isUpdate = $isFirst || WUPDATE;

    $ntxs = [];
    $stable = 0;
    $finish = false;
    $lastHeight = -1;
    $index = -1;

    for( ; $isUpdate; )
    {
        for( ;; )
        {
            $txs = $wk->getTransactions( $address, $batch, isset( $after ) ? $after : null );
            if( $txs === false )
            {
                sleep( 1 );
                continue;
            }
            break;
        }

        foreach( $txs as $tx )
        {
            $id = $tx['id'];
            $txjson = json_encode( $tx );
            $txkey = getTxKey( $wk, $tx );

            $mtx = $triples->getUno( 1, $id );
            if( $mtx !== false && $txjson === gzinflate( $mtx[2] ) && $txkey === (int)$mtx[0] )
            {
                if( !isset( $stableTxKey ) )
                    $stableTxKey = $txkey;

                if( ++$stable === 50 )
                {
                    $finish = true;
                    break;
                }
            }
            else
            {
                $stable = 0;
                unset( $stableTxKey );
                $ntxs[] = [ $txkey, $id, gzdeflate( $txjson, 9 ) ];
            }
        }

        $wk->log( __FUNCTION__ . ': new transactions = ' . count( $ntxs ) );

        if( !$finish && isset( $txs[$batch - 1]['id'] ) )
        {
            $after = $txs[$batch - 1]['id'];
            continue;
        }

        if( count( $ntxs ) === 0 )
            break;

        if( isset( $stableTxKey ) )
            $triples->query( 'DELETE FROM ts WHERE r0 > ' . $stableTxKey );

        krsort( $ntxs );
        $triples->merge( $ntxs );
        break;
    }

    return $triples->query( 'SELECT * FROM ts ORDER BY r0 ASC' );
}

function dAppReproduce( $wk, $qs, $functions, $startId = null, $bypass = null )
{
    global $working;
    global $height;

    $qpos = [];
    $qtx = [];
    $n = count( $qs );
    for( $i = 0; $i < $n; ++$i )
    {
        $r = $qs[$i]->fetch();
        if( $r === false )
        {
            $qpos[$i] = 0;
            $qtx[$i] = false;
        }
        else
        {
            $qpos[$i] = (int)$r[0];
            $qtx[$i] = json_unpack( $r[2] );
        }
    }

    $working = true;
    $ai = 0;

    /*
    $atxs = [
        [
            'type' => 16,
            "sender" => "3PGqsK9tPic1UnZh8GDyHwr7udDa4WFpzdS",
            'dApp' => "3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF",
            "payment" => [
                [
                    "amount" => 500000000000,
                    "assetId" => "6XtHjpXbs9RRJP2Sr9GUyVqzACcby9TkThHXnjVC5CDJ"
                ]
            ],
            "call" => [
                "function" => "replenishWithOneToken",
                "args" => [
                    [
                        "type" => "integer",
                        "value" => 256518483160
                    ],
                    [
                        "type" => "integer",
                        "value" => 256938997818
                    ]
                ]
            ],
            "height" => "2599061",
            "applicationStatus" => "succeeded",
            'id' => 'atx'
        ],
        [
            'type' => 16,
            "sender" => "3PGqsK9tPic1UnZh8GDyHwr7udDa4WFpzdS",
            'dApp' => "3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF",
            "payment" => [
                [
                    "amount" => 208768996846,
                    "assetId" => "6XtHjpXbs9RRJP2Sr9GUyVqzACcby9TkThHXnjVC5CDJ"
                ]
            ],
            "call" => [
                "function" => "withdraw",
            ],
            "height" => "2599061",
            "applicationStatus" => "succeeded",
            'id' => 'atx'
        ],
    ];
    */

    for( ; $working; )
    {
        $cpos = PHP_INT_MAX;
        $ci = false;
        for( $i = 0; $i < $n; ++$i )
        {
            $pos = $qpos[$i];
            if( $pos > 0 && $pos < $cpos )
            {
                $cpos = $pos;
                $ci = $i;
            }
        }

        if( $ci === false )
        {
            if( isset( $atxs[$ai] ) )
            {
                $tx = $atxs[$ai];
                ++$ai;
            }
            else
                break;
        }
        else
        {
            $tx = $qtx[$ci];
        
            $r = $qs[$ci]->fetch();
            if( $r === false )
            {
                $qpos[$ci] = 0;
                $qtx[$ci] = false;
            }
            else
            {
                $qpos[$ci] = (int)$r[0];
                $qtx[$ci] = json_unpack( $r[2] );
            }
        }

        //$wk->log( $tx['id'] . ' (' . $tx['height'] . ')' );

        if( $tx['applicationStatus'] !== 'succeeded' )
            continue;

        $type = $tx['type'];
        $sender = $tx['sender'];
        $id = $tx['id'];
        $height = $tx['height'];

        if( $height > HIBLOCK )
            continue;

        if( isset( $startId ) && !isset( $isStarted ) )
        {
            if( $startId === $id )
            {
                $isStarted = true;
            }
            else
            {
                $bypass( $tx );
                continue;
            }
        }

        if( isset( $functions['*'] ) )
        {
            $functions['*']( $tx );
            continue;
        }

        if( !isset( $functions[$type] ) )
        {
            //if( $sender === $dApp )
                //$wk->log( 'w', 'bypass dApp activity (' . $type . ') (' . $id . ')' );
            continue;
        }

        //validator();

        if( $type === 16 )
        {
            $dApp = $tx['dApp'];
            $function = $tx['call']['function'];

            if( !isset( $functions[$type][$dApp][$function] ) )
            {
                if( isset( $functions[$type][$dApp]['*'] ) )
                {
                    $functions[$type][$dApp]['*']( $tx );
                    continue;
                }

                if( isset( $functions[$type]['*'] ) )
                {
                    $functions[$type]['*']( $tx );
                    continue;
                }

                //$wk->log( 'w', 'notice skipping dApp(' . $dApp . ')->' . $function . '() (' . $id . ')' );
                continue;
            }

            $functions[$type][$dApp][$function]( $tx );
        }
        else
        {
            if( isset( $functions[$type][$sender] ) )
                $functions[$type][$sender]( $tx );
        }
    }

    $wk->log( 's', 'dAppReproduce() done' );
}

function getDecompiledScript( $script )
{
    global $wk;
    $decompile = $wk->fetch( '/utils/script/decompile', true, $script );
    if( $decompile === false || false === ( $decompile = $wk->json_decode( $decompile ) ) || !isset( $decompile['script'] ) )
        exit( $wk->log( 'e', 'getDecompiledScript() failed' ) );
    
    return $decompile['script'];
}

function getCompiledScript( $script )
{
    global $wk;
    $compile = $wk->fetch( '/utils/script/compile', true, $script );
    if( $compile === false || false === ( $compile = $wk->json_decode( $compile ) ) || !isset( $compile['script'] ) )
        return false;    
    return $compile['script'];
}

function getLastScript( $address )
{
    $wk = new deemru\WavesKit;
    $wk->setNodeAddress( 'https://api.wavesplatform.com' );
    $fetch = '/v0/transactions/set-script?sender=%s&sort=desc&limit=1';
    if( defined( 'HISTAMP' ) )
        $fetch .= '&timeEnd=' . HISTAMP;
    $txs = $wk->fetch( sprintf( $fetch, $address ) );
    if( $txs === false || false === ( $txs = $wk->json_decode( $txs ) ) || !isset( $txs['data'][0]['data']['script'] ) )
        exit( $wk->log( 'e', 'getLastScript( '. $address .' ) failed' ) );
    
    return $txs['data'][0]['data']['script'];
}

function getDecimals( $asset )
{
    global $wk;

    if( $asset === 'WAVES' || $asset === null )
        return 8;

    static $db;
    if( isset( $db[$asset] ) )
        return $db[$asset];

    $info = $wk->json_decode( $wk->fetch( '/assets/details/' . $asset ) );
    if( isset( $info['decimals'] ) )
    {
        $db[$asset] = $info['decimals'];
        return $info['decimals'];
    }
    return false;
}

function getName( $asset )
{
    global $wk;

    if( $asset === 'WAVES' || $asset === null )
        return 'WAVES';

    static $db;
    if( isset( $db[$asset] ) )
        return $db[$asset];

    $info = $wk->json_decode( $wk->fetch( '/assets/details/' . $asset ) );
    if( isset( $info['name'] ) )
    {
        $db[$asset] = $info['name'];
        return $info['name'];
    }
    return false;
}

function power( $base, $bp, $exponent, $ep, $rp, $isUp )
{
    $base = $base / pow( 10, $bp );
    $exponent = $exponent /  pow( 10, $ep );
    $result = pow( $base, $exponent );
    $result *= pow( 10, $rp );
    $result = $isUp === true ? ceil( $result ) : ( $isUp === false ? floor( $result ) : round( $result ) );
    $result = intval( $result );
    return $result;
}

function poweroot( $base, $bp, $exponent, $ep, $rp, $isUp )
{
    $base = gmp_mul( $base, '1000000000000000000000000' );
    $result = gmp_strval( gmp_sqrt( $base ) );
    $tail = (int)substr( $result, -8 );
    $result = (int)substr( $result, 0, -8 );
    if( $isUp === true )
        $result += $tail === 0 ? 0 : 1;
    return $result;
}

function absorber( $c, $data )
{
    foreach( $data as $r )
    {
        $key = $r['key'];
        $value = $r['value'];

        if( !isset( $value ) )
            unset( $c->db[$key] );
        else
            $c->db[$key] = $value;
    }
}
