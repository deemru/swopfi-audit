# Swop.fi audit

(Translated by [@elenaili](https://github.com/elenaili))

## Survey scope

The Swop.fi project was surveyed as of May 13, 2021, inclusive.

The object of the survey is end-to-end operation of the project's smart contracts in the Waves mainnet.

At the time of the survey, the project is an ecosystem of 22 smart contracts. An external dependency of this ecosystem is the staking functionality for USDN, EURN, and NSBT tokens.

Since the staking functionality is a part of the [Neutrino protocol](https://neutrino.at), staking is not subject to a full-fledged audit. Because of that, accounting and distribution of charges are considered the responsibility of the [Neutrino protocol](https://neutrino.at).

## Project structure

First of all, we define the scope of the project based on the open information provided on the official [swop.fi](https://swop.fi) site. Next, we analyze the output of the [`test-addresses`](test/test-addresses.php) script used to find the addresses used in contracts. When all the addresses involved in the project are identified, we analyze the contracts installed for these addresses.

Final set of contracts:

```php
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
```

Smart contracts are parsed automatically using the [test-contracts](test/test-contracts.php) script. This script finds the latest smart contract installation transaction, decompiles the smart contract using the Waves node and writes this information to a file. The file name is prefixed by a checksum generated for the decompiled contract code.

Based on the data obtained, smart contracts can be represented as:

- 16 exchange pools:
  - 12 CPMM pools with USDN staking
  - 2 FLAT pools with USDN staking
  - 1 CPMM pool with EURN staking
  - 1 CPMM pool with a dual NSBT and USDN staking
- Early-birds (SWOP distribution for early investors)
- Governance (SWOP distribution for staking)
- Governance-LP (collection and conversion of fees into SWOP for Governance)
- Farming (rewards for staking of exchange pool tokens)
- Pools (registry of pools)
- Voting

The decompiled RIDE binary is similar to the original source, with the names of functions and variables preserved. To analyze contracts, it's sufficient to have a decompiled version of the code. However, such elements as [FOLD](https://docs.waves.tech/en/ride/fold-macro) macros may complicate the analysis, so it's advisable to have the source code for contracts.

Based on the source code from the [swopfi/swopfi-smart-contracts](https://github.com/swopfi/swopfi-smart-contracts) repository alone, one can't exactly co-align the source code and decompiled code without more analysis, which complicates the audit.

RECOMMENDED introducing verifiable version control for the source code installed as smart contracts.

For aligning with the source code, the `WGIT` option in [test-contracts](test/test-contracts.php) is used. Using this option and co-aligning contracts, the following matches were obtained:

- CPMM pools: [`0024b99ca77bb89544405043b434455e8d06125f`: other_cpmm.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/0024b99ca77bb89544405043b434455e8d06125f/dApps/other_cpmm.ride)
- FLAT pools: [`b1149700ac09b0f346537187adc31a27f19db0f4`: flat.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/b1149700ac09b0f346537187adc31a27f19db0f4/dApps/flat.ride)
- CPMM pool (EURN): [`6d25e6255f2c468118fae4fa4dd89677c25adfbc`: waves_eurn.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/6d25e6255f2c468118fae4fa4dd89677c25adfbc/dApps/waves_eurn.ride)
- Early-birds: [`0b41ebdc18f31e6cbe3ad3e7e60993e2f6d9b4b6`: earlybirds.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/0b41ebdc18f31e6cbe3ad3e7e60993e2f6d9b4b6/dApps/SWOP/earlybirds.ride)
- Governance: [`a68796c2073c623b0d1d6f7185f52ec75be9a7fa`: governance.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/a68796c2073c623b0d1d6f7185f52ec75be9a7fa/dApps/SWOP/governance.ride)
- Governance-LP: [`452881ecc97dc689a4fad92ca91e147c28f6eb93`: wallet_verifier.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/452881ecc97dc689a4fad92ca91e147c28f6eb93/dApps/SWOP/wallet_verifier.ride)
- Farming: [`452881ecc97dc689a4fad92ca91e147c28f6eb93`: farming.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/452881ecc97dc689a4fad92ca91e147c28f6eb93/dApps/SWOP/farming.ride)
- Pools: [`924f1ed9b4c902bbfa96494e4067c4d7b17ff36d`: oracle.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/924f1ed9b4c902bbfa96494e4067c4d7b17ff36d/dApps/SWOP/oracle.ride)
- Voting: [`a68796c2073c623b0d1d6f7185f52ec75be9a7fa`: voting.ride](https://raw.githubusercontent.com/swopfi/swopfi-smart-contracts/a68796c2073c623b0d1d6f7185f52ec75be9a7fa/dApps/SWOP/voting.ride)

## Security

### General

Swop.fi smart contracts are audited in the general context of security of user funds, regardless of the functionality of internal recalculation.

Balances are maintained in the [account data storages](https://docs.waves.tech/en/blockchain/account/account-data-storage) of smart contracts. Each smart contract has its own storage not overlapping with others and represented as a key/value database. The keys consist of a pool address, user address, and a fixed string, which is equivalent to a typical storage of tokens in the blockchain, where a key in the database can be represented as the user address plus token ID. In both cases, the value is the balance of tokens.

Token ID is uniquely determined from the logic and is immutable at runtime.

The user's address is determined from the sender address of the user transaction, which ensures that [all the checks applicable for the user account](https://docs.waves.tech/en/ride/functions/verifier-function) are passed (for example, actions set by the user's smart contract in addition to the default signature verification).

Contracts don't have the functionality of external decrease of entries in user data, except for the actions of the user themselves, for example, when withdrawing funds.

The contracts also don't have the functionality of changing the entry owner, which unequivocally associates withdrawal only with the address that deposited the funds. Hence, the closest analog of accounting in Swop.fi contracts is native leasing implemented as the [Lease](https://docs.waves.tech/en/blockchain/transaction-type/lease-transaction) and [Lease Cancel](https://docs.waves.tech/en/blockchain/transaction-type/lease-cancel-transaction) transactions.

Since `_` is used as a separator of semantic key parts when accessing the value, while semantic postfixes to the keys contain `_`, it is NOT RECOMMENDED to use the separator in other parts of the key, because as the functionality expands further there is a possibility of accessing a key different from the meaning.

### Administration

Automated analysis of contracts for public keys is done by the [test-admins](test/test-admins.php) script. This script finds the latest transaction of smart contract installation, decompiles the smart contract using Waves node, and finds all the used variable entries that match public keys. Then it displays the summary on the screen.

All contracts use an overridden [Verifier function](https://docs.waves.tech/en/ride/functions/verifier-function) that ensures enhanced security at contract administration. This function requires 2 (out of 3 possible) admin signatures when updating a smart contract. 3 admin's public keys are set in the code in each smart contract. Hence, if 2 administrative keys are compromised, the project might completely get out of control.

RECOMMENDED to improve the [key management system](https://en.wikipedia.org/wiki/Key_management) and use a separate smart contract for it.

The automated analysis demonstrates that out of 3 addresses that correspond to admin public keys, 2 addresses have outgoing transactions in the Waves mainnet. RECOMMENDED to use admin keys solely for administration to minimize the risk of [side-channel attack](https://en.wikipedia.org/wiki/Side-channel_attack).

The auxiliary public keys are also set in the contract code. Such public keys are allowed to perform certain actions on behalf of their addresses, for example, start and stop contracts, run staking functions, and do other actions that don't require advanced privileges. This limited functionality is documented in detail for each contract, however, such fields as `fee` and `feeAssetId` are not checked. RECOMMENDED to add appropriate checks to prevent fee amount manipulation.

In the [`farming.ride`](https://github.com/swopfi/swopfi-smart-contracts/commit/452881ecc97dc689a4fad92ca91e147c28f6eb93#diff-6ccb5b9241c7ad143dd5a8adc495c3e869251c186fdf2cb20db08f53677801e8) contract, a revised model is used to allow auxiliary functions: it uses addresses instead of public keys. RECOMMENDED to use a unified permission model, since different approaches that are essentially the same complicate the system.

At the moment, no centralized location has been identified for logging of administrative actions and their reasons. Although everything is open and verifiable on the blockchain, it's RECOMMENDED to keep a public record of administrative actions, for example, in the source code repository, to facilitate analysis and action verification by independent experts.

## Functionality and verification

Functionality analysis includes the analysis of every contract's function in every operating mode, building logical dependencies on other contracts, analyzing operation at boundary conditions, analyzing of permissions for function calls.

When analyzing the functionality, consistency between the declared and programmed logic is checked.

Functionality verification for a smart contract includes comparing of multiple values obtained by different methods (if possible) with the independent functionality emulation on contract data in the Waves mainnet.

For a target contract, the process is as follows:

- Transactions are loaded for the contract's address and for all the auxiliary addresses whose data is required for proper operation of the contract.
- Transactions are played back in the script code corresponding to the contract's functional logic.
- In the final state, the target values are calculated.
- Verification is considered successful if the calculation is consistent with the expectation.

### CPMM pools

All pools with the `pool-cpmm` prefix from the project structure table are considered, because the common essential part in these contracts is the same.

Contracts define the `init`, `replenishWithTwoTokens`, `withdraw`, `exchange`, `shutdown`, `activate`, and `takeIntoAccountExtraFunds` functions.

The `verify` function used to verify outgoing transactions slightly varies in the three contract versions, for example, usually a signature of one admin is required, but for the NSBT contract only `adminPubKeyStaking` is required. RECOMMENDED to unify this logic in the next versions, since it doesn't make an essential difference.

An internal `takeIntoAccountExtraFunds` call is allowed, interactions with the Neutrino protocol are allowed for staking. The NSBT contract can also call the `exchange` function of the `3PHaNgomBkrvEL2QnuJarQVJa71wjw9qiqG` contract (this is needed to exchange the received Waves rewards for USDN maintained by the NSBT contract).

When called, the `takeIntoAccountExtraFunds` function accepts as an argument the number of tokens used as a fee for staking/unstaking on Neutrino. These tokens must stay unaccounted on the pool's token balances, because they are going to be used to call the staking functions.

The balance calculation includes the full account balance on the blockchain and the staked funds balance. Therefore, all the funds received, regardless of their originating reason, will generally add to the pool.

The `shutdown` and `activate` functions can be called by any admin and are responsible for suspending calls of other functions and activating them respectively. Suspending a contract means completely freezing of the contract logic without any action with the funds on the contract.

The `init` function has no arguments and is used to initialize the contract. Initialization arguments are basically payments attached to the function call. However, the order of tokens doesn't directly affect the functionality, but for the users it's more convenient that pairs follow the same order as commonly used by exchanges. Up to the first 7 characters are taken from the token names, the `s` prefix is added, and the strings are concatenated with the `_` delimiter, the resulting string is considered the name of the pool's share token issued at initialization.

The number of decimal digits is determined by averaging over the tokens from payments. The number of initial share tokens is calculated by a formula that multiplies the roots of the initial payment values and divides the product by 10 to the power of the number of decimal digits calculated in the previous step.

[`0024b99ca77bb89544405043b434455e8d06125f`: other_cpmm.ride#L122-L127](https://github.com/swopfi/swopfi-smart-contracts/blob/0024b99ca77bb89544405043b434455e8d06125f/dApps/other_cpmm.ride#L122-L127):

```scala
        let shareDecimals = (pmtDecimalsA + pmtDecimalsB) / 2
        let shareInitialSupply = fraction(
            pow(pmtAmountA, pmtDecimalsA, 5, 1, pmtDecimalsA, HALFDOWN),
            pow(pmtAmountB, pmtDecimalsB, 5, 1, pmtDecimalsB, HALFDOWN),
            pow(10, 0, shareDecimals, 0, 0, HALFDOWN)
        )
```

We found no essential effect of this formula on further functionality, because the number of tokens is afterwards used only as a share in the total amount. It means that it's enough to have a precision corresponding to the initial tokens, which is reflected in the formula for calculating decimal digits.

The `exchange` function can be called by any blockchain user. As a payment, it accepts one of the tokens used at initialization and a minimum value corresponding to the minimum expected amount of tokens to be obtained by the user in exchange. If all the conditions are met, it returns to the user another token used at initialization, in the amount calculated by the CPMM formula between the tokens minus the `commission` fee (0.3%) withheld by the pool. Part of this fee is also sent to the Governance-LP contract (0.12%).

Finally, a check is made that on the balance there's currently enough tokens to be sent to the user. Since the pool functionality assumes active use of Neutrino staking, the amount of tokens on the contract balance may be insufficient and must first be withdrawn from Neutrino staking.

The function is split in two parts: one part exchanges the first token for the second, and other part does the reverse, unnecessarily duplicating the code. RECOMMENDED combining the semantic parts, because the essential difference is only the exchange direction.

Currently, for 3 contracts (`other_cpmm.ride`, `nsbt_usdn.ride`, and `waves_eurn.ride`) the differences are only in calculations and permissions relating to staking. RECOMMENDED combining the code of CPMM contracts, despite the fact that not all functionality will be used in all pools.

The `replenishWithTwoTokens` and `withdraw` functions are called by users that are shared participants of the pool (or want to become such participants).

For the `replenishWithTwoTokens` function, the tokens for the input payment must be provided in the same order as during the pool initialization. Their ratio of shares in the pool must not differ by more than the `slippageTolerance` set by the user in the function argument and accepting positive values less than the maximum `slippageToleranceDelimiter`.

The function accounts for the staking fee if there is USDN, NSBT, or EURN among tokens. This fee is withdrawn from the user payment before calculating their share.

If all the checks are passed, the user receives share tokens in the amount proportional to the minimum share of the two pool's tokens in the total amount of share tokens.

The `withdraw` function does a reverse operation: share tokens are exchanged into pool tokens whose amounts are proportional to the share of the operation's share tokens in the total amount of share tokens of the pool.

[`0024b99ca77bb89544405043b434455e8d06125f`: other_cpmm.ride (L190-L194)](https://github.com/swopfi/swopfi-smart-contracts/blob/0024b99ca77bb89544405043b434455e8d06125f/dApps/other_cpmm.ride#L190-L194):

```scala
    let (pmtAmount, pmtAssetId) = (i.payments[0].amount, i.payments[0].assetId)

    # block for accounting the cost of commissions for staking operations
    let amountToPayA = pmtAmount.fraction(balanceA, shareAssetSupply).deductStakingFee(assetIdA)
    let amountToPayB = pmtAmount.fraction(balanceB, shareAssetSupply).deductStakingFee(assetIdB)
```

The function accounts for the staking fee if there are USDN, NSBT, or EURN among the tokens. This fee is deducted from the user payment after calculating the share of their tokens.

We noticed that the `withdraw` function in the `other_cpmm.ride` contract doesn't return share tokens back to the user if there's an error in the `hasEnoughBalance` step. RECOMMENDED to fix this behavior or use the above recommendation to combine contracts into a single generalized contract.

CPMM pools are verified by the [`test-cpmm`](test/test-cpmm.php) script. Since the contract's scope is localized and no additional accounting is done, verification is limited to comparing the calculated state with the state in Waves mainnet.

Verification completes successfully, no errors or negative effects of rounding were found. The `takeIntoAccountExtraFunds` function is called by an external handler with an established value of 18 minSponsoredFee, with the average call frequency of once every ~1456 blocks (~ once a day).

### FLAT pools

All pools with the `pool-flat` prefix from the project scope table are included. They correspond to the `flat.ride` contract.

Similarly to the CPMM pools, the contracts have the `init`, `replenishWithTwoTokens`, `withdraw`, `exchange`, `shutdown`, `activate`, and `takeIntoAccountExtraFunds` functions.

Distinctions from CPMM are as follows: 1) There is the `replenishWithOneToken` function that, unlike `replenishWithTwoTokens`, can issue share tokens based on a single token in the payment, 2) There is an additional `estimatedAmountToReceive` argument in the `exchange` function, and 3) The [formula for calculating FLAT](https://medium.com/swop-fi/swop-fi-pricing-5f89ab8766ac) is more sophisticated.

[`b1149700ac09b0f346537187adc31a27f19db0f4`: flat.ride (L101-L112)](https://github.com/swopfi/swopfi-smart-contracts/blob/b1149700ac09b0f346537187adc31a27f19db0f4/dApps/flat.ride#L101-L112):

```scala
func invariantCalc(x: Int, y: Int) = {
    let sk = skewness(x, y)
    fraction(
        x + y,
        scaleValue8,
        pow(sk, scaleValue8Digits, alpha, alphaDigits, scaleValue8Digits, UP)
    ) + 2 * fraction(
        pow(fraction(x, y, scaleValue8), 0, 5, 1, scaleValue8Digits / 2, DOWN),
        pow(sk - beta, scaleValue8Digits, alpha, alphaDigits, scaleValue8Digits, DOWN),
        scaleValue8
    )
}
```

We noticed that the formula to calculate the `invariantCalc` invariant takes a square root of the product of total balances of pool tokens. The resulting product is returned to a 64-bit value, causing an [integer overflow](https://en.wikipedia.org/wiki/Integer_overflow) when each token has a balance slightly exceeding 30 million.

We got a confirmation from the Swop.fi team that as the new RIDE version is released, this calculation will use 512-bit integer precision.

Since other functions doesn't seem to have any fundamental differences from CPMM, below we discuss only the `exchange` and `replenishWithOneToken` functions.

The `exchange` function can be called by any blockchain user. As a payment, it accepts one of the tokens used to initialize the contract by the `init` function. As arguments, it accepts `estimatedAmountToReceive` (the expected amount of tokens) and `minAmountToReceive` (the minimum amount of tokens). If all the conditions are met, it returns to the user another token used at initialization, in an amount calculated by the FLAT formula between the tokens minus the `commission` fee (0.05%) withheld by the pool. Part of this fee is also sent to the Governance-LP contract (0.02%).

If the value of `estimatedAmountToReceive` is incorrect, the contract makes another 5 attempts between the expected and minimum (`minAmountToReceive`) value, and if one of the attempts provides a correct value, this value is considered the amount of exchange tokens.

We noticed that if the expected amount of tokens can't be obtained, the fee is withheld when calculating the intermediate result and then repeated when exiting the function. Thus, a double fee is withheld. In the updated contract in the repository, this bug has been corrected (the fee is withheld once), but as of the audit date this bug hasn't been corrected yet. (Revision of May 26, 2021: The bug has been fixed completely in the source code and installed contracts.)

The minimum payment amount that can be exchanged is set in the contract at 10000000 so as to eliminate fluctuations in the formula for FLAT calculation in the case of small exchanged values.

We noticed that the first check of the arguments for whether the resulting exchanged value falls in the interval between `exchangeRatioLimitMin` and `exchangeRatioLimitMax` relatively to the payment can be improved, because `minAmountToReceive` is logically closer to `estimatedAmountToReceive` than to `pmtAmount`, and that's why it should be checked against it. A suggestion how to improve it has been communicated to the Swop.fi team.

In the `exchange` and `replenishWithOneToken` functions, it's checked that the final balance of one of the contract's tokens doesn't exceed the balance of the other token more than thrice.

To get the pool's share tokens in the `replenishWithOneToken` function, it is necessary to provide, as a payment, one of the pool's tokens and arguments which define the parameters of the internal virtual exchange of this token for the second token in the pool. It means that virtual exchange takes place first, and then the function behaves similarly to `replenishWithTwoTokens`. It is checked that the virtual exchange result and the user's payment have a ratio close to the ratio of the final balances after the virtual exchange and payment.

We noticed that `ratioShareTokensInA` and `ratioShareTokensInB` are calculated with the staking fee, but the `ratioVirtualBalanceToVirtualReplenish` ratio is calculated without this fee. Therefore, the perfect ratio for the user (when `ratioShareTokensInA` and `ratioShareTokensInB` are equal) might fail the preliminary check by `slippageValueMinForReplenish` and` slippageValueMaxForReplenish`. RECOMMENDED to account for the staking fee while calculating any ratios.

We noticed that the result of `replenishWithOneToken` (i.e., the pool's share tokens), can be directly passed to the `withdraw` function that will return the pool's tokens according to the current ratio between the tokens and the user's share. However, if this functionality is presented as an exchange, then variant of exchange using `replenishWithOneToken` and `withdraw` doesn't contain a fee deduction as in the `exchange` function, which might be used to avoid paying a fee from large-scale exchanges to the pool.

RECOMMENDED to include fee deduction for virtual exchange in the `replenishWithOneToken` function, similarly to `exchange`.

We noticed that the `suspendSuspicious` subfunction that automatically suspends the contract doesn't send payments that could have been received as part of the transaction back to the user. However, this functionality is correctly implemented in the `nsbt_usdn.ride` and `waves_eurn.ride` contracts.

RECOMMENDED to use a single code for common parts to minimize regressions.

Finally, a check is made that on the balance there's currently enough tokens to be sent to the user. Since the pool functionality assumes active use of Neutrino staking, the amount of tokens on the contract balance may be insufficient and must first be withdrawn from Neutrino staking.

The function is split in two parts: one part exchanges the first token for the second, and other part does the reverse, unnecessarily duplicating the code. RECOMMENDED to combine semantic parts, since the essential difference is only the exchange direction (this recommendation has been met in the current source code of the contract).

FLAT pools are verified by the [`test-flat`](test/test-flat.php) script. Since the contract's scope is localized and no additional accounting is done, verification is limited to comparing the calculated state with the state in Waves mainnet.

Verification completes successfully, no errors or negative effects of rounding were found. The `takeIntoAccountExtraFunds` function is called by an external handler with an established value of 18 minSponsoredFee, with the average call frequency of once every ~1474 blocks (~ once a day) for the `3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1` pool.

Withholding a double fee, for example, for the `3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1` pool, affects ~9% of exchanges, with an additional amount of +23% of fees collected in favor of the pools, which is equivalent to the total fee increase from 0.05% to 0.06%.

### Nuances of `exchange` functions

In the CPMM and FLAT pools, a successful call of an `exchange` function is not guaranteed in a competitive environment. If multiple users are doing exchanges simultaneously, then when executing `exchange`, the data expected by users when calling the function could have changed when other users ran `exchange`.

In order to increase execution guarantees, users may take risks by lowering the minimum amount of expected tokens.

Since user transactions get into the UTX pool (the unconfirmed transactions pool), they become public. The timestamp of a transaction is valid for execution in the Waves network, if it's in the interval between [2 hours in the past or 1.5 hours in the future](https://docs.waves.tech/en/blockchain/transaction/). It means that a user whose `exchange` transaction hasn't been executed because the pool's token balance is out of acceptable limits, finds themselves in a situation where their transaction can still be executed within 2 hours.

This nuance may be completely unexpected for users without a deep background in the Waves blockchain operation. Therefore, RECOMMENDED to add a function argument or a new function similar to `exchange`, or add to the default `exchange` function algorithm the option to set an explicit limit on exchange execution, for example, in the current and the next block. This can help reduce future execution uncertainty for a transaction that hasn't been executed right away.

### Nuances of staking

As we already noted, the functionality of the `exchange` and `withdraw` functions in the CPMM and FLAT pools might fail to be executed if the pool balance doesn't have the required amount of tokens that might be reserved by Neutrino staking.

Since staking is controlled by an external handler, there might be a situation when a user wants to execute a large transaction, but gets an error that's not recorded anywhere and is visible only to the user. That's why the user is forced to split a large transaction into parts that can be executed in the context of the available balance in the pool. Then, as the partial transactions are executed, the external handler might report an insufficient balance.

The implemented staking model is optimal at the moment, due to the limitations of the RIDE language. Swop.fi team confirmed that, as the new RIDE version is released, this functionality will be automated in the `exchange` and `withdraw` calls so that the external handler is not involved anymore.

### Early-birds (SWOP distribution for early investors)

The contract at the address `3PJuspTjxHhEJQjMEmLM5upiGQYCtCi5LyD` with one `claimSWOP` function without parameters. This function determines the current amount of a reward in SWOP tokens for the calling address and, if successful, sends the reward to this address.

The list of addresses with available rewards was calculated outside of the contract (not subject to audit) and recorded using Data transactions before the smart contract execution started. The sum of rewards in these records is equal to 999999.99999115 SWOPs, before the start of execution, 1000000.00000000 SWOPs were transferred to the contract during the contract initialization (the `5gVX4xgeg8rQ3Kz18rcQyPFo7BpsdqyKrqQM9JetVtSm` transaction).

Since each `claimSWOP` call takes into account the previously received rewards, the total reward at the end of the period exactly equals the originally recorded reward amount, regardless of rounding errors during the intermediate calls.

All the functionality declared on the site is present, no deviations were found.

The contract at the `3PJuspTjxHhEJQjMEmLM5upiGQYCtCi5LyD` address is verified by the [`test-early_birds`](test/test-early_birds.php) script. This script emulates the functionality of the original contract and calculates:
- The total initial SWOP reward based on the Data transaction records.
- The total SWOP amount received by users over the entire period.
- The difference between the initial and calculated SWOP amounts.
 
The execution result with [floating-point numbers](https://en.wikipedia.org/wiki/Floating-point_arithmetic) is consistent with the expectation.

```log
2021.05.10 17:05:04    INFO: users_total_share = 999999.99999115
2021.05.10 17:05:04    INFO: users_total_claimed = 999999.99999115
2021.05.10 17:05:04    INFO: diff = 0.00000000
```

The execution result with integers in the [fraction](https://docs.waves.tech/ru/ride/functions/built-in-functions/math-functions#fraction) function (consistent with the current precision in the Waves blockchain).

```log
2021.05.10 17:37:49    INFO: users_total_share = 999999.99999115
2021.05.10 17:37:49    INFO: users_total_claimed = 999999.99999115
2021.05.10 17:37:49    INFO: diff = 0.00000000
```

The calculation is fully consistent with the expectation, the verification is successful.

### Governance (SWOP distribution for staking)

The contract at the `3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS` address with the `airDrop`, `lockSWOP`, `withdrawSWOP`, `claimAndWithdrawSWOP`, `claimAndStakeSWOP`, `updateWeights`, `shutdown`, and `activate` functions.

The `activate`/`shutdown` functions can be called only by admins and set the `keyActive` key to the value that enables/disables calling of other functions, respectively.

The `lockSWOP`, `withdrawSWOP`, `claimAndWithdrawSWOP`, and `claimAndStakeSWOP` functions ensure accounting and rewarding of staking. The calculation of shares in the total reward is calculated every time these functions are called, in the `keyUserLastInterest` key.

The total reward is calculated by the `airDrop` function in the `keyLastInterest` key as the ratio of the reward to the total staked funds.

Therefore, the users' reward is calculated as the difference between the current value of `keyLastInterest` and the last value of `keyUserLastInterest` multiplied by the  staked amount of the user's tokens. After that, `keyUserLastInterest` is written to the current value of `keyLastInterest`.

Since each time the reward is re-calculated, the negative impact may be caused by rounding in the `fraction` function in the case the user frequently calls the functions. However, as the verification shows (see below), this impact is insignificant.

The `withdrawSWOP` function also takes into account the funds that are in voting. Hence, in order to fully withdraw previously added funds and rewards, a user needs to release them in the Voting contract's `votePoolWeight` function. This restriction affects only withdrawal, with the reward still calculated based on the full amount of user funds.

The `updateWeights` function writes the new values of reward shares for the pools. The function can be called on behalf of admins, including local admins (`adminPubKeyStartStop` and `adminPubKeyWallet`).

This function resolves certain issues that emerged in the first versions of updates of reward shares for the pools (for more details, see Farming verification). It verifies that the sum total of shares is 100% and the rewards are updated in the future relative to the current height.

However, there is no match check between the new value of the `keyRewardPoolFractionPrevious` key and the current `keyRewardPoolFractionCurrent` being changed (such an error already occurred before). There is also no connection with the Voting contract.

RECOMMENDED to add match checks between the previous reward value and the previous current value and check the new reward shares against the user votes in the Voting contract.

The contract at the `3PLHVWCqA9DJPDbadUofTohnCULLauiDWhS` address is verified by the [`test-governance`](test/test-governance.php) script. This script emulates the functionality of the original contract and calculates:
- The total amount of SWOP tokens staked.
- The total amount of SWOP tokens accounted by the contract.
- The total amount of SWOP tokens of all users (including all rewards).
- The difference between the total SWOP amount of users and on the contract.

The execution result with [floating-point numbers](https://en.wikipedia.org/wiki/Floating-point_arithmetic) is consistent with the expectation.

```log
2021.05.10 15:43:36    INFO: 387373.34068475
2021.05.10 15:43:36    INFO: 390137.62330409
2021.05.10 15:43:36    INFO: 390137.62330409
2021.05.10 15:43:36    INFO: 0.00000000
```

The execution result when using integers in the [fraction](https://docs.waves.tech/ru/ride/functions/built-in-functions/math-functions#fraction) function (consistent with the current precision in the Waves blockchain) has a discrepancy of about 0.004%.

```log
2021.05.10 15:43:30    INFO: 387369.95550148
2021.05.10 15:43:30    INFO: 390135.82677428
2021.05.10 15:43:30    INFO: 390133.90819713
2021.05.10 15:43:30    INFO: 1.91857715
```

At the same time (at the time of the `2N4ZJooWPCRJkV8DhSmLpxde7P6zbquJ2Wi1V6HVvLAH` transaction), on the corresponding contract in the Waves mainnet there are `390135.80534807` SWOP tokens, which overlaps the value of `390133.90819713` that would be needed should all users decide to withdraw their funds, including rewards.

The verification is successful.

The first version of the contract contained an error, which was corrected by a complete recalculation of all the keys involved and updating them using Data transactions run by admins. This verification helped to confirm correctness of the values of the updated keys. At the moment, the key values are totally consistent with the verification keys.

### Governance-LP (collection and conversion of fees into SWOP for Governance)

The contract at the `3P6J84oH51DzY6xk2mT5TheXRbrCwBMxonp` address without external functions. The functionality of the contract allows certain actions on behalf of the contract address, subject to certain conditions, with the local admin of these actions being the `Czn4yoAuUZCVCLJDRfskn8URfkwpknwBTZDbs1wFrY7h` public key (it corresponds to the `3PPupsBVHgDXaRhyot6MbpTxminsAMs` address.

The following calls are permitted:
- Call of the `exchange` function in any exchange pool registered in Pools (registry of pools).
- Call of the `airDrop` and `updateWeights` functions in Governance (SWOP distribution for staking).
- Call of the `updatePoolInterest` function in Farming (rewards for staking of exchange pool tokens).
 
Further call policy is determined by an external handler. RECOMMENDED to provide the source code of the external handler to analyze its operation.

Recommendations on the fee amount are given in Security > General.

The `3P6J84oH51DzY6xk2mT5TheXRbrCwBMxonp` contract is verified by the [test-governance_lp](test/test-governance_lp.php) script that counts all the fees received from the exchange pools, calculates the exchanges, fees for transaction calls, payments to Governance performed by the `airDrop` function, as well as the `updateWeights` and `updatePoolInterest` calls.

The result (at the time of the `GRtSotTKorHMekjLUkipo3fNLidZG71RGvsvHRVPd4gQ` transaction) is used to generate an output that includes all the funds that were received, spent on exchange into SWOP and fees, the total amount of SWOP tokens sent, the average payment amount and frequency of payments.

```log
2021.05.11 09:04:55    INFO: updateWeights (2513318:2513318)
2021.05.11 09:04:56    INFO: updateWeights (2523445:10127)
2021.05.11 09:04:57    INFO: updateWeights (2533572:10127)
2021.05.11 09:04:57    INFO: updateWeights (2543710:10138)
2021.05.11 09:04:58    INFO: updateWeights (2553887:10177)
2021.05.11 09:04:59    INFO: updateWeights (2563991:10104)
2021.05.11 09:05:00    INFO: updateWeights (2574093:10102)
2021.05.11 09:05:01    INFO: updateWeights (2584195:10102)
2021.05.11 09:05:01 SUCCESS: dAppReproduce() done
2021.05.11 09:05:01    INFO: total plus:
2021.05.11 09:05:01    INFO: |- 9026.89193923 WAVES
2021.05.11 09:05:01    INFO: |- 409973.003691 USD-N
2021.05.11 09:05:01    INFO: |- 9614.966839 USDT
2021.05.11 09:05:01    INFO: |- 1.16836061 WBTC
2021.05.11 09:05:01    INFO: |- 1330.839868 NSBT
2021.05.11 09:05:01    INFO: |- 2065.55 WavesCommunity
2021.05.11 09:05:01    INFO: |- 12019.42145014 WEST
2021.05.11 09:05:01    INFO: |- 0.00920272 sWAVES
2021.05.11 09:05:01    INFO: |- 2178.790796 Neutrino EUR
2021.05.11 09:05:01    INFO: |- 2558.049003 USDTLP
2021.05.11 09:05:01    INFO: |- 17611.30682622 SWOP
2021.05.11 09:05:01    INFO: |- 4.49876329 WETH
2021.05.11 09:05:01    INFO: |- 2594.484477 USD Coin
2021.05.11 09:05:01    INFO: |- 1266.748861 USDCLP
2021.05.11 09:05:01    INFO: |- 1098.65332541 Curve DAO Token
2021.05.11 09:05:01    INFO: |- 165.04413677 Freeliquid
2021.05.11 09:05:01    INFO: |- 218660.02104438 SIGN
2021.05.11 09:05:01    INFO: total minus:
2021.05.11 09:05:01    INFO: |- 9016.96393923 WAVES
2021.05.11 09:05:01    INFO: |- 1.16836061 WBTC
2021.05.11 09:05:01    INFO: |- 1330.817395 NSBT
2021.05.11 09:05:01    INFO: |- 2178.790796 Neutrino EUR
2021.05.11 09:05:01    INFO: |- 9614.491952 USDT
2021.05.11 09:05:01    INFO: |- 2558.022994 USDTLP
2021.05.11 09:05:01    INFO: |- 12019.42145014 WEST
2021.05.11 09:05:01    INFO: |- 2065.55 WavesCommunity
2021.05.11 09:05:01    INFO: |- 409951.257513 USD-N
2021.05.11 09:05:01    INFO: |- 17611.30682622 SWOP
2021.05.11 09:05:01    INFO: |- 4.49876329 WETH
2021.05.11 09:05:01    INFO: |- 1266.471992 USDCLP
2021.05.11 09:05:01    INFO: |- 2587.923330 USD Coin
2021.05.11 09:05:01    INFO: |- 1098.65332541 Curve DAO Token
2021.05.11 09:05:01    INFO: |- 218660.02104438 SIGN
2021.05.11 09:05:01    INFO: |- 164.44810538 Freeliquid
2021.05.11 09:05:01    INFO: final balance:
2021.05.11 09:05:01    INFO: |- 9.92800000 WAVES
2021.05.11 09:05:01    INFO: |- 21.746178 USD-N
2021.05.11 09:05:01    INFO: |- 0.474887 USDT
2021.05.11 09:05:01    INFO: |- 0.00000000 WBTC
2021.05.11 09:05:01    INFO: |- 0.022473 NSBT
2021.05.11 09:05:01    INFO: |- 0.00 WavesCommunity
2021.05.11 09:05:01    INFO: |- 0.00000000 WEST
2021.05.11 09:05:01    INFO: |- 0.00920272 sWAVES
2021.05.11 09:05:01    INFO: |- 0.000000 Neutrino EUR
2021.05.11 09:05:01    INFO: |- 0.026009 USDTLP
2021.05.11 09:05:01    INFO: |- 0.00000000 SWOP
2021.05.11 09:05:01    INFO: |- 0.00000000 WETH
2021.05.11 09:05:01    INFO: |- 6.561147 USD Coin
2021.05.11 09:05:01    INFO: |- 0.276869 USDCLP
2021.05.11 09:05:01    INFO: |- 0.00000000 Curve DAO Token
2021.05.11 09:05:01    INFO: |- 0.59603139 Freeliquid
2021.05.11 09:05:01    INFO: |- 0.00000000 SIGN
2021.05.11 09:05:01    INFO: totalAirDropped = 16633.42648856 SWOP
2021.05.11 09:05:01    INFO: mean airDrop = 7.50723814 SWOP
2021.05.11 09:05:01    INFO: mean period = 61.16 blocks
```

All functions are called with the expected frequency; the `updatePoolInterest` invariant correction function was not called when there were no transactions in the pool.

The balance of the `3P6J84oH51DzY6xk2mT5TheXRbrCwBMxonp` address at the time of running the script matches the calculated balance, which proves that funds are spent as expected.

The verification is considered successful, because it meets the main declared Swop.fi principles described on the website, namely, the conversion of incoming fees into SWOP and their distribution across the SWOP staking participants in the Governance contract.

### Farming (rewards for staking of pool tokens)

The contract at the `3P73HDkPqG15nLXevjCbmXtazHYTZbpPoPw` address with the `init`, `initPoolShareFarming`, `updatePoolInterest`, `lockShareTokens`, `withdrawShareTokens`, and `claim` functions.

The `init` function is called once, it releases and commits SWOP (the main token of the project), the identifier of which is recorded in the key of the `keySWOPid` variable. The amount of issued tokens is fixed and sent using the function to the address from the function call argument (this functionality is commented out in the current script version). In this case, it's sent to the Early-birds address according to the declared distribution of 1000000 SWOP to early investors (see the Early-birds functionality and verification).

The `init` function is protected from repeat calls, but it doesn't contain any restrictions on the call address, which may result in an inappropriate call of this function during initialization of a similar project on other addresses. RECOMMENDED to add a restriction on calling this function by the admin.

The `initPoolShareFarming` function is called every time when a pool is added to the farming accounting. The function can be called only on behalf of the contract, which means that signatures of two admins are required. The function initializes by zeros the `keyShareTokensLocked` and `keyLastInterest` keys for the pool from the argument. It's not possible to work with the pool without initializing these keys.

The `lockShareTokens`, `withdrawShareTokens`, and `claim` functions account for staking of the pool's tokens and receiving staking rewards. The calculation of shares in the total reward is calculated every time these functions are called, in the `keyUserLastInterest` key.

Unlike Governance, where a reward is generated only in the `airDrop` function, in the Farming contract a reward is generated whenever the blockchain height grows, therefore, the reward is calculated at each call of the audited functions and then written to the `keyLastInterest` pool's key.

Therefore, the users' reward is calculated as the difference between the current value of `keyLastInterest` and the last value of `keyUserLastInterest` multiplied by the staked amount of the user's tokens. After that, `keyUserLastInterest` is written to the current value of `keyLastInterest`.

Since each time the reward is re-calculated, the negative impact may be caused by rounding in the `fraction` function in the case the user frequently calls the functions. However, as the verification shows (see below), this impact is insignificant.

The calculation of the reward is dynamic, because it can change depending on the state of the keys in the Governance contract that are set by the `updateWeights` function.

At the present moment, the `keyTotalRewardPerBlockCurrent` and `keyTotalRewardPerBlockPrevious` keys are set by admins in the Data transaction (`CiA8tvkzCLbVYGfhLgkEBRhuHg5ajJc6c55TGEr4SQxYOP` at the value that corresponds to the declared reward amount of 1000000 SWOP per year and is equal to 189751395, which at the rate of 1440 blocks per day corresponds to (100000000000000 / 189751395 / 1440 = ) ~366 days.

The `keyRewardUpdateHeight` key and the `keyRewardPoolFractionCurrent` and `keyRewardPoolFractionPrevious` keys for each pool change with each call of `updateWeight` and start to be used since the next call of the Farming functions.

The current approach still has the drawback where the absence of calls to the `lockShareTokens`, `withdrawShareTokens`, and `claim` functions during the `keyRewardPoolFractionCurrent` validity period may result in an incorrect calculation of `keyLastInterest`.

RECOMMENDED to switch to a calculation model that takes into account more than 2 periods.

The `3P73HDkPqG15nLXevjCbmXtazHYTZbpPoPw` contract is verified by the scripts: 1) the [test-farming_v1](test/test-farming_v1.php) script that takes into account the registration of pools, changing of pool rewards, staking, and user rewards, depending on the current reward up to the time of the `5TjBrivfmEDnMr5hLDnwNLDs63dim8Y1jM8Zc59Zd9ta` transaction that switches to the current keys of rewards and 2) the [test-farming_v2](test/test-farming_v2.php) script that starts its accounting when the `test-farming_v1` script ends and accounts for registration of pools, change of rewards for the pools, staking, and user rewards, in the current version of the contract.

As a result of execution, a database is created that is used to make two independent calculations, with a separate calculation of rewards generated by pools regardless of users and the total amount of claimed and unclaimed rewards.

If the values match, it means that the logic for reward calculation is correct in every step.

When verifying this contract, we encountered difficulties with interpreting the results, since the logic of the previous contract versions was incorrect, that is, had two main issues: 1) There was no intermediate period for changing the reward amount, the change took place immediately after setting new values, and the new values set the height of the past reward change, and 2) rewards for pools were set manually by admins using Data transactions that weren't verified by any contract code and that's why contained errors, such as 1) too frequent change with no calls in-between and 2) incorrect values of the previous reward per block that wasn't equal to the previous current reward.

As a result of these issues, this contract can't be verified using the data from the mainnet without correcting the data first.

By default, the correction is enabled, but it can be disabled using the `WFIX = 0` key.

When calculating with correction, verification shows that the current logic is valid. However, the pre-existing errors have already contributed to the calculation of the `keyLastInterest` and `keyUserLastInterest` values.

Therefore, there is no way to simultaneously prove the validity of the logic and the correctness of calculation with a full match between the calculated values and the data in the Waves mainnet for a given contract.

That's why the verification is split into 2 stages: 1) verifies the previous version of the contract 2) verifies the current version of the contract.

The result of `test-farming_v1` before the `5TjBrivfmEDnMr5hLDnwNLDs63dim8Y1jM8Zc59Zd9ta` transaction when using [floating point numbers](https://en.wikipedia.org/wiki/Floating-ypoint_arithmeticQuest) is fully consistent with the expectation, except for the `3PK7Xe5BiedRyxHLuMQx5ey9riUQqvUths2` pool where there occurred a recalculation with the above-mentioned overlapping of rewards in the case when there were no transactions for the period, causing a balance mismatch in favor of one farming participant.

```log
2021.05.14 08:15:55    INFO: 3PHaNgomBkrvEL2QnuJarQVJa71wjw9qiqG: 15207.22180181 === 15207.22180180 (0.00000000)
2021.05.14 08:15:55    INFO: 3PACj2DLTw3uUhsUmT98zHU5M4hPufbHKav: 9033.19500857 === 9033.19500822 (0.00000034)
2021.05.14 08:15:55    INFO: 3P8FVZgAJUAq32UEZtTw84qS4zLqEREiEiP: 11528.72691823 === 11528.72691786 (0.00000036)
2021.05.14 08:15:55    INFO: 3P2V63Xd6BviDkeMzxhUw2SJyojByRz8a8m: 10291.09990831 === 10291.09990830 (0.00000000)
2021.05.14 08:15:55    INFO: 3PMDFxmG9uXAbuQgiNogZCBQASvCHt1Mdar: 557.33720094 === 557.33720032 (0.00000061)
2021.05.14 08:15:55    INFO: 3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1: 14367.69467757 === 14367.69467724 (0.00000032)
2021.05.14 08:15:55    INFO: 3P6DLdJTP2EySq9MFdJu6beUevrQd2sVVBh: 3228.33477040 === 3228.33477039 (0.00000000)
2021.05.14 08:15:55    INFO: 3PK7Xe5BiedRyxHLuMQx5ey9riUQqvUths2: 2229.34873433 === 2649.41618876 (-420.06745443)
2021.05.14 08:15:55    INFO: 3PDWi8hjQJjXhyTpeaiEYfFKWBd1iC4udfF: 5748.26497227 === 5748.26497226 (0.00000000)
2021.05.14 08:15:55    INFO: 3P27S9V36kw2McjWRZ37AxTx8iwkd7HXw6W: 19405.00424771 === 19405.00424738 (0.00000032)
2021.05.14 08:15:55    INFO: 3PNEC4YKqZiMMytFrYRVtpW2ujvi3aGXRPm: 8641.82517407 === 8641.82517407 (0.00000000)
2021.05.14 08:15:55    INFO: 3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF: 9528.55977044 === 9528.55977010 (0.00000034)
2021.05.14 08:15:55    INFO: 3PNr615DPhHpCJSq1atHYKKnoauWGHsYWBP: 1606.35545581 === 1606.35545581 (0.00000000)
2021.05.14 08:15:55    INFO: 3P9o2H6G5d2xXBTfBEwjzHc16RLSZLFLQjp: 962.79847675 === 962.79847686 (-0.00000011)

2021.05.14 08:15:55    INFO: TOTAL: 112335.76711721 === 112755.83456944 (-420.06745223)
```

Due to the indicated issues, in the previous versions of the contract, the `test-farming_v2` script uses the difference between the balance snapshots at the stages of calling the `updateWeight` function.

The execution result of `test-farming_v2` when using [floating point numbers](https://en.wikipedia.org/wiki/Floating-point_arithmetic) is fully consistent with the expectation.

```log
2021.05.13 19:29:36    INFO: 3PHaNgomBkrvEL2QnuJarQVJa71wjw9qiqG: 3712.31231792 === 3712.31231792 (0.00000000)
2021.05.13 19:29:36    INFO: 3P2V63Xd6BviDkeMzxhUw2SJyojByRz8a8m: 458.77216823 === 458.77216823 (0.00000000)
2021.05.13 19:29:36    INFO: 3P8FVZgAJUAq32UEZtTw84qS4zLqEREiEiP: 884.93378039 === 884.93378039 (0.00000000)
2021.05.13 19:29:36    INFO: 3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1: 6428.17570436 === 6428.17570436 (0.00000000)
2021.05.13 19:29:36    INFO: 3P6DLdJTP2EySq9MFdJu6beUevrQd2sVVBh: 439.76797570 === 439.76797570 (0.00000000)
2021.05.13 19:29:36    INFO: 3PMDFxmG9uXAbuQgiNogZCBQASvCHt1Mdar: 293.29923307 === 293.29923307 (0.00000000)
2021.05.13 19:29:36    INFO: 3PACj2DLTw3uUhsUmT98zHU5M4hPufbHKav: 572.44991224 === 572.44991224 (0.00000000)
2021.05.13 19:29:36    INFO: 3PDWi8hjQJjXhyTpeaiEYfFKWBd1iC4udfF: 85.62947621 === 85.62947621 (0.00000000)
2021.05.13 19:29:36    INFO: 3PK7Xe5BiedRyxHLuMQx5ey9riUQqvUths2: 479.93347967 === 479.93347967 (0.00000000)
2021.05.13 19:29:36    INFO: 3P27S9V36kw2McjWRZ37AxTx8iwkd7HXw6W: 2356.95117924 === 2356.95117924 (0.00000000)
2021.05.13 19:29:36    INFO: 3PNEC4YKqZiMMytFrYRVtpW2ujvi3aGXRPm: 333.56171747 === 333.56171747 (0.00000000)
2021.05.13 19:29:36    INFO: 3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF: 1131.09730363 === 1131.09730363 (0.00000000)
2021.05.13 19:29:36    INFO: 3PNr615DPhHpCJSq1atHYKKnoauWGHsYWBP: 113.96708620 === 113.96708620 (0.00000000)
2021.05.13 19:29:36    INFO: 3P9o2H6G5d2xXBTfBEwjzHc16RLSZLFLQjp: 243.11972147 === 243.11972162 (-0.00000014)
2021.05.13 19:29:36    INFO: 3P4Ftyud3U3xnuR8sTc1RvV4iQD62TcKndy: 877.41840214 === 877.41840214 (0.00000000)
2021.05.13 19:29:36    INFO: 3PKy2mZqnvT2EtpwDim9Mgs6YvCRe4s85nX: 757.29646488 === 757.29646488 (0.00000000)
2021.05.13 19:29:36    INFO: SNAPSHOT (2574093..2584195): 19168.68592290 === 19168.68592304 (-0.00000014)

2021.05.13 19:29:36    INFO: TOTAL (2513318..2588950): 143512.77506640 === 143512.77506599 (0.00000040)
```

The execution result of `test-farming_v2` with integers in the [fraction](https://docs.waves.tech/ru/ride/functions/built-in-functions/math-functions#fraction) function (consistent with the current precision in the Waves blockchain) has a discrepancy of about 0.4%.

```log
2021.05.13 19:31:35    INFO: 3PHaNgomBkrvEL2QnuJarQVJa71wjw9qiqG: 3712.31223160 === 3708.67344200 (3.63878960)
2021.05.13 19:31:35    INFO: 3P2V63Xd6BviDkeMzxhUw2SJyojByRz8a8m: 458.77209920 === 458.55583408 (0.21626512)
2021.05.13 19:31:35    INFO: 3P8FVZgAJUAq32UEZtTw84qS4zLqEREiEiP: 884.93369498 === 884.93080070 (0.00289428)
2021.05.13 19:31:35    INFO: 3PPH7x7iqobW5ziyiRCic19rQqKr6nPYaK1: 6428.17569384 === 6332.73987577 (95.43581807)
2021.05.13 19:31:35    INFO: 3P6DLdJTP2EySq9MFdJu6beUevrQd2sVVBh: 439.76793598 === 439.26011159 (0.50782439)
2021.05.13 19:31:35    INFO: 3PMDFxmG9uXAbuQgiNogZCBQASvCHt1Mdar: 293.29917912 === 292.90962781 (0.38955131)
2021.05.13 19:31:35    INFO: 3PACj2DLTw3uUhsUmT98zHU5M4hPufbHKav: 572.44988124 === 572.44656032 (0.00332092)
2021.05.13 19:31:35    INFO: 3PDWi8hjQJjXhyTpeaiEYfFKWBd1iC4udfF: 85.62939824 === 85.17197389 (0.45742435)
2021.05.13 19:31:35    INFO: 3PK7Xe5BiedRyxHLuMQx5ey9riUQqvUths2: 479.93345620 === 479.86192142 (0.07153478)
2021.05.13 19:31:35    INFO: 3P27S9V36kw2McjWRZ37AxTx8iwkd7HXw6W: 2356.95116284 === 2354.41363225 (2.53753059)
2021.05.13 19:31:35    INFO: 3PNEC4YKqZiMMytFrYRVtpW2ujvi3aGXRPm: 333.56166700 === 333.55389593 (0.00777107)
2021.05.13 19:31:35    INFO: 3PNi1BJendWYYe2CRnqpfLoYxUZ6UTcx3LF: 1131.09726266 === 1126.46310838 (4.63415428)
2021.05.13 19:31:35    INFO: 3PNr615DPhHpCJSq1atHYKKnoauWGHsYWBP: 113.96707234 === 113.56244949 (0.40462285)
2021.05.13 19:31:35    INFO: 3P9o2H6G5d2xXBTfBEwjzHc16RLSZLFLQjp: 243.11971024 === 243.01434278 (0.10536746)
2021.05.13 19:31:35    INFO: 3P4Ftyud3U3xnuR8sTc1RvV4iQD62TcKndy: 877.41837894 === 854.57190817 (22.84647077)
2021.05.13 19:31:35    INFO: 3PKy2mZqnvT2EtpwDim9Mgs6YvCRe4s85nX: 757.29639104 === 757.08193395 (0.21445709)
2021.05.13 19:31:35    INFO: SNAPSHOT (2574093..2584195): 19168.68521546 === 19037.21141853 (131.47379693)

2021.05.13 19:31:35    INFO: TOTAL (2513318..2588950): 143512.76927241 === 142914.39338612 (598.37588629)
```

Taking into account that the Farming contract itself doesn't accumulate SWOP tokens, but issues them on user request, this discrepancy doesn't manifest as an accumulated error, but affects only the declared emission of 1 million SWOP tokens per year that can be adjusted upwards by the discrepancy percentage.

The verification is successful.

### Pools (registry of pools)

The contract at the `3PEbqViERCoKnmcSULh6n2aiMvUdSQdCsom` address with the `addPool` and `renamePool` functions. Functions can be called only on behalf of the contract, which requires 2 admin signatures, respectively.

The `addPool` function registers the address and name in the pool database. Three entries are made:
- The pool index that is incremented at each call.
- The key with the pool address and name in the value.
- The entry by the key in `keyPoolsListName` with the addresses of all the added pools, separated by commas.
 
The `renamePool` function changes the name of the pool.

The pool name is checked against two strings separated by `_`.

The functionality of this contract is counter-intuitive, because the built-in functionality is either redundant and not planned to be used (because the index and the full list of pools are not used in other contracts) or the full-fledged management functionality is missing (not just for renaming, but also for deleting).

The functionality of initialization when starting working with new pools is present in other contracts, for example, in the `initPoolShareFarming` function of the Farming contract.

Maintaining the reward information is implemented in another Governance contract: the information is updated by the `updateWeights` function.

RECOMMENDED to consolidate the pool information in a single place and unify the logic of initialization/addition/exclusion of pools in the system workflow.

The contract at the `3PEbqViERCoKnmcSULh6n2aiMvUdSQdCsom` address is verified by the [`test-pools`](test/test-pools.php) script. This script emulates the functionality of the original contract and checks the internal logic against transaction processing in the Waves mainnet.

Verification is successful, no difference found between the logic and practical operation.

### Voting

The contract at the `3PQZWxShKGRgBN1qoJw6B4s9YWS9FneZTPg` address with one `votePoolWeight` function.

The `votePoolWeight` function is called by Governance users that have a positive staking balance. The arguments are lists of pools and votes for them; this prototype was inherited from the first version of the function. In the present version, the functionality of processing more than one entry in the list is not implemented, although it was supported before. Looks like this functionality is going to be revised in the next versions. In the context of the current analysis, we assume that the function's call can change accounting only for one pool.

The current version of the `votePoolWeight` function has two main logical branches of behavior: 1) the user increases their SWOPs voted for the pool and 2) the user decreases their SWOPs voted for the pool.

In the current contract version, the power factor was introduced. This factor is equal to 1 for the period `durationFullVotePower` (~1 day) and then decreases linearly down to the value of `minVotePower` (0.1) at the end of the vote period.

The resulting entries are used in 3 structures:
- The user structure for each pool includes: 1) voted SWOPs 2) vote power given the power factor 3) current period 4) SWOPs frozen during the current period.
- The pool structure includes: 1) SWOPs voted for the pool 2) vote power given the power factor 3) ID of the current period.
- The common structure contains: 1) Total voted SWOPs 2) vote power given the power factor 3) ID of the current period.

When increasing the voted SWOPs for the pool, the power factor is taken into account. When withdrawing SWOPs, the vote power decreases proportionally. Such a difference might be counter-intuitive for the user who voted at the beginning of the period, at the end of the period, and then decided to withdraw some of their voted SWOPs, because in the case of vote withdrawal, all tokens will have the same vote power.

Although no logic errors were found in the voting code, the code used to decrease the vote has an unreasonably sophisticated and counter-intuitive implementation. RECOMMENDED to refactor the code used for vote decrease to minimize branching and simplify understanding.

[`a68796c2073c623b0d1d6f7185f52ec75be9a7fa`: voting.ride (L171-L179)](https://github.com/swopfi/swopfi-smart-contracts/blob/a68796c2073c623b0d1d6f7185f52ec75be9a7fa/dApps/SWOP/voting.ride#L171-L179):

```scala
        let userPoolFreezeSWOPnew = if userPoolVotePeriod == currPeriod then userPoolFreezeSWOP else userPoolVoteSWOP
        let userPoolFreezeSWOP2 = min([userPoolFreezeSWOP, userPoolVoteSWOP])
        let userPoolFreezeSWOPnew2 = min([userPoolFreezeSWOPnew, userPoolVoteSWOPnew])
        let userPoolActiveVoteSWOPnew = userPoolFreezeSWOPnew2 + if userPoolVoteSWOP - userPoolFreezeSWOP == 0 then 0 
            else fraction(userPoolActiveVoteSWOP - userPoolFreezeSWOP,userPoolVoteSWOPnew - userPoolFreezeSWOPnew2, userPoolVoteSWOP-userPoolFreezeSWOP)
        let userPoolActiveVoteDiff = userPoolActiveVoteSWOPnew - if userPoolVotePeriod == currPeriod then userPoolActiveVoteSWOP else userPoolVoteSWOP
        let newUnvoted = max([0, removePoolVote - if userPoolVotePeriod == currPeriod then userPoolVoteSWOP - userPoolFreezeSWOP2 else 0])
        let userUnvotedNew = newUnvoted + if userUnvotedPeriod == currPeriod then userUnvoted else 0 
        let userUnvotedPeriodNew = if newUnvoted > 0 then currPeriod else userUnvotedPeriod
```

The contract at the `3PQZWxShKGRgBN1qoJw6B4s9YWS9FneZTPg` address is verified by the [`test-voting`](test/test-voting.php) script. This script emulates the functionality of the original contract and checks the internal logic of `updateWeights` calls against the transaction processing in the Waves mainnet.

Verification consists in checking the votes and consistency of reward shares set by the `updateWeight` function of the Governance contract to the shares based on the values of the `kPoolVoteSWOP` and `kTotalVoteSWOP` keys in the first version of the contract and the values of the `kPoolStruc` and `kTotalStruc` keys in the current version.

```log
2021.05.13 19:45:53    INFO: updateWeigth at 6LBwYRt8eRxQW5MLnFpzxmzQdQx7LaVM19NCg6h46Ydy
2021.05.13 19:45:53    INFO: updateWeigth at DiDWymP49xFj7qChAn8MiERvEecUZWcQK8Hnv2ZJPcDB
2021.05.13 19:45:54    INFO: updateWeigth at 9KZFMcpbA8kqJDvsyZkzKkjWdAuHGZ9GV3sThLhdanPt
2021.05.13 19:45:54    INFO: updateWeigth at 5Qrtx7fwVNBZjxiPY1kvNz1viSNPEPdTcToRRqUoHQC7
2021.05.13 19:45:54    INFO: updateWeigth at G2FAjAUZE6p1M6Tb2ashELagLUSGm2RyoMsnGUoUxGQp
2021.05.13 19:45:54 WARNING: 3P6DLdJTP2EySq9MFdJu6beUevrQd2sVVBh: 43221064 !== 43221062
2021.05.13 19:45:54    INFO: updateWeigth at 5nrCviH5bFPGQ26F4tygB2aG5kbmeDGuEy7E8BAMMg5T
2021.05.13 19:45:54    INFO: updateWeigth at 483oGqcwuHa8S3YsNvDrWx9FghupZMpcfVkt25EoL7MV
2021.05.13 19:45:54    INFO: updateWeigth at HEThD2ngcx2qEn8TS2ygu6YJzRbW5QCMMcP9PxFDSvwW
```

Verification is successful, the maximum error between the set share and calculated share is ~0.00001%.

## Conclusions

All the functionality described on the official site is present and runs according to the declared logic. As of the time of the audit, all the identified errors in the functionality were promptly corrected. No critical vulnerabilities or bugs were found as a result of the audit.

As of the audit completion, a bug with charging a double exchange fee when the calculation mismatches `estimatedAmountToReceive`, was fixed in the source code, but still persists in the currently running version of the FLAT pool contracts. (Revision of May 26, 2021: The bug has been fixed completely in the source code and installed contracts.)

The verification is totally successful for all the current contracts with the accuracy of rounding to an integer. The current version of the Farming contract is fully verified using the method of comparing intermediate snapshots of balances. The early versions of the Farming contract and its execution on the Waves mainnet contained logic errors that caused increased emission for some users in the amount not exceeding 0.17% of the total emission at the time of the audit.

The core project functionality (exchanges, liquidity, staking, farming) is completely decentralized and doesn't require maintenance in the long run.

The existing dependency on external event handlers in the case of staking, exchange of fees and SWOP distribution, recording of vote results, although they have no negative impact on the system operation if run correctly (which was confirmed during verification of contracts), degrade the decentralized properties of contracts nevertheless. This dependency on external handlers has probably 2 reasons: 1) lack of the relevant functionality in the RIDE language, for example, calling functions of other contracts, and 2) oversophistication of the logic when trying to implement the functionality in the current RIDE 4 language version. RECOMMENDED to overcome all the external dependencies to ensure complete decentralization of the project as new RIDE versions are released.

The recommendations given during the audit are not mandatory, but should be taken into account and carefully pondered on should there be decided to skip their implementation in the next project versions.
