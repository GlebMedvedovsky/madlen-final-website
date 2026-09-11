# Production publisher: актуальная инструкция

Единый план выпуска, preflight, полный backend kit, настройки GitHub/Netcup, первый release, document root и rollback находятся в [NETCUP_RELEASE_RU.md](NETCUP_RELEASE_RU.md).

Промежуточная подготовка из шести файлов заменена итоговым комплектом. Теперь необходимы новые классы, обновление Composer autoload и аддитивная migration production identity. Старый малый preview-overlay повторно не применять.

До отдельного согласованного окна publisher и GitHub gate остаются выключенными. Node/npm на Netcup не нужны. `config:cache` запрещён. Реальные данные и секреты комплект не содержит.
