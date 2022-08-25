# Requestor LiveAgent import
Requirements: PHP7.4

!!! POZOR !!! 
- Nez zacnete importovat do RQ musite spravci Vasi instance predat zip soubor s exportem priloh
- Do adresare ExportUsers musite naplnit alespon soubor ExportUsersRQ.csv exportem vsech uzivatelu z RQ
- Pokud chcete porovnat vsechny uzivatele RQ a LA musite naplnit i soubor full_customers_LA.csv a v url pouzit ?userCompareOn=1

Pouziti:
- nastavit config.php
- v index.php opravit logiku a nazvy Vasich service front ve funkci convertDepartmentToService()
- naplnit soubor ExportUsers/ExportUsersRQ.csv popr i full_customers_LA.csv (z admnistrace jednotlivych systemu RQ a LA)
- spustit localhost?from=0&to=100 (provede export prvnich 100 ticketu) nebo localhost?ticketCode=xxx-ccc-xxx (provede export jednoho ticketu)
- vytvori se soubor export_from0_to100.xml
- vytvori se slozka Import se soubory priloh exportovanych ticketu
- vytvori se soubor export_from0_to100_excludedTickets.csv, kde jsou tickety, ktere nebyly naimportovany protoze nejsou uzavrene
- vytvori se soubor export_from0_to100_missingRqUsers.csv, kde jsou emaily uzivatelu kteri jsou v esportovanem ticketu ale nejsou v RQ a je treba je pred importem export_from0_to100.xml nejdrive rucne v administraci RQ naimportovat
- spoustet opakovane dle uvazeni dalsi bloky from=101&to=999 (vzdy vyexportuje pozadovany rozsah ticketu do jednoho xml souboru a do Import adresare prilohy)
- pokud jiz mate vyexportovano vse, zabalit slozku Import do jednoho zip souboru
- predat zip soubor spravci RQ instance, aby nakopiroval 
- po potvrzeni od spravce muzete zacit importovat pres Administrace -> Import
