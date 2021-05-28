# Swop.fi аудит

**По-русски** | [In english](README.md)

- [Область исследования](swopfi-audit.ru.md#область-исследования)
- [Состав проекта](swopfi-audit.ru.md#состав-проекта)
- [Безопасность](swopfi-audit.ru.md#безопасность)
  - [Общее](swopfi-audit.ru.md#общее)
  - [Администрирование](swopfi-audit.ru.md#администрирование)
- [Функционал и верификация](swopfi-audit.ru.md#функционал-и-верификация)
  - [Пулы CPMM](swopfi-audit.ru.md#пулы-cpmm)
  - [Пулы FLAT](swopfi-audit.ru.md#пулы-flat)
  - [Нюансы работы функций exchange](swopfi-audit.ru.md#нюансы-работы-функций-exchange)
  - [Нюансы работы стейкинга](swopfi-audit.ru.md#нюансы-работы-стейкинга)
  - [Early-birds (распределение SWOP для ранних инвесторов)](swopfi-audit.ru.md#early-birds-распределение-swop-для-ранних-инвесторов)
  - [Governance (распределение SWOP за стейкинг)](swopfi-audit.ru.md#governance-распределение-swop-за-стейкинг)
  - [Governance-LP (сбор и конвертация комиссий в SWOP для Governance)](swopfi-audit.ru.md#governance-lp-сбор-и-конвертация-комиссий-в-swop-для-governance)
  - [Farming (награда за стейкинг токенов обменных пулов)](swopfi-audit.ru.md#farming-награда-за-стейкинг-токенов-обменных-пулов)
  - [Pools (реестр пулов)](swopfi-audit.ru.md#pools-реестр-пулов)
  - [Voting (голосование)](swopfi-audit.ru.md#voting-голосование)
- [Выводы](swopfi-audit.ru.md#выводы)

# Скрипты верификации
- Скрипты расположены в папке [test](test) данного репозитория
- Скрипты основаны на функциональности [WavesKit](https://github.com/deemru/WavesKit)
- Используйте `composer install` для установки зависимостей
- Расположите репозиторий [`swopfi-smart-contracts`](https://github.com/swopfi/swopfi-smart-contracts) в той же директории, что и данный репозиторий
- Рекомендуется использовать локальную ноду Waves с REST API (по умолчанию это 127.0.0.1:6869)
- Вы можете запускать скрипты в любом порядке
