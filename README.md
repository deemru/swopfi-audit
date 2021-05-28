# Swop.fi audit

[По-русски](README.ru.md) | **In english**

- [Survey scope](swopfi-audit.en.md#survey-scope)
- [Project structure](swopfi-audit.en.md#project-structure)
- [Security](swopfi-audit.en.md#security)
  - [General](swopfi-audit.en.md#general)
  - [Administration](swopfi-audit.en.md#administration)
- [Functionality and verification](swopfi-audit.en.md#functionality-and-verification)
  - [CPMM pools](swopfi-audit.en.md#cpmm-pools)
  - [FLAT pools](swopfi-audit.en.md#flat-pools)
  - [Nuances of exchange functions](swopfi-audit.en.md#nuances-of-exchange-functions)
  - [Nuances of staking](swopfi-audit.en.md#nuances-of-staking)
  - [Early-birds (SWOP distribution for early investors)](swopfi-audit.en.md#early-birds-swop-distribution-for-early-investors)
  - [Governance (SWOP distribution for staking)](swopfi-audit.en.md#governance-swop-distribution-for-staking)
  - [Governance-LP (collection and conversion of fees into SWOP for Governance)](swopfi-audit.en.md#governance-lp-collection-and-conversion-of-fees-into-swop-for-governance)
  - [Farming (rewards for staking of pool tokens)](swopfi-audit.en.md#farming-rewards-for-staking-of-pool-tokens)
  - [Pools (registry of pools)](swopfi-audit.en.md#pools-registry-of-pools)
  - [Voting](swopfi-audit.en.md#voting)
- [Conclusions](swopfi-audit.en.md#conclusions)

# Verification scripts
- The scripts consolidated at [test](test) folder of this repo
- The scripts based on [WavesKit](https://github.com/deemru/WavesKit) functionality
- Use `composer install` to setup dependencies
- Place [`swopfi-smart-contracts`](https://github.com/swopfi/swopfi-smart-contracts) repo the same folder as this repo
- It is recommended to run a local Waves node with REST API (default is 127.0.0.1:6869)
- You can run the scripts in any order
