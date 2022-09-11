# Requestor LiveAgent import
Requirements: PHP7.4, curl, zip

!!! POZOR !!! 
- Nez zacnete importovat do RQ musite spravci Vasi instance RQ predat zip soubor s exportem priloh

!!! PRIPRAVA !!!
- po stazeni projektu vytvorte adresare Import, ImportXML, MirrorUsers a dejte jim prava cteni pro web server
- Do adresare MirrorUsers musite naplnit alespon soubor ExportUsersRQ.csv exportem vsech uzivatelu z RQ
- nastavit config.php url na vasi instanci LA, API key a heslo kterym budou zaheslovane vysledne zip soubory
- v index.php opravit logiku a nazvy Vasich service front ve funkci convertDepartmentToService() pripadne dalsi nastaveni dale v kodu
- naplnit soubor MirrorUsers/ExportUsersRQ.csv popr i full_customers_LA.csv (z admnistrace jednotlivych systemu RQ a LA)

Poznamka:
- Pokud chcete porovnat vsechny uzivatele RQ a LA musite naplnit i soubor full_customers_LA.csv a v url pouzit ?userCompareOn=1

Pouziti:
- spustit localhost?from=0&to=100 (provede export prvnich 100 ticketu) nebo localhost?ticketCode=xxx-ccc-xxx (provede export jednoho ticketu)
- vytvori se soubor export_from0_to100.xml
- vytvori se slozka Import se soubory priloh exportovanych ticketu
- vytvori se soubor export_from0_to100_excludedTickets.csv, kde jsou tickety, ktere nebyly naimportovany protoze nejsou uzavrene
- vytvori se soubor export_from0_to100_missingRqUsers.csv, kde jsou emaily uzivatelu kteri jsou v esportovanem ticketu ale nejsou v RQ a je treba je pred importem export_from0_to100.xml nejdrive rucne v administraci RQ naimportovat
- spoustet opakovane dle uvazeni dalsi bloky from=101&to=999 (vzdy vyexportuje pozadovany rozsah ticketu do jednoho xml souboru a do Import adresare prilohy)
- vzdy po vyexportovani bloku vyse je nutno potvrdit, ze nedoslo k zastaveni na Vasi strane zavolanim localhost?commitData=1 (prenese prave pripravenou cast kumulovanych dat do finalnich souboru). Slouzi pro potvrzeni ze napr. nevyprsel timeout predchoziho volani na Vasi strane a stejna davka se jiz nebude znovu pregenerovavat
- pokud jiz mate vyexportovano cca 2 tis ticketu tj. jste provedli from=1800&to=2000 je doporuceno zavolat url pro download ziskanych dat na Vas disk
- dokud neni proveden download XML, jsou data XML importu, chybejicich uzivatelu a vyloucenych ticketu kumulovana do velkych souboru coz urychli nasledny import diky mensimu poctu nutnych importu
- download dat ma dva prikazy from=0&to=2000&download=XML nebo from=0&to=2000&download=FILES kde 0-2000 slouzi jen k Vasemu pojmenovani vygenerovanych zip souboru pro import
- predat zip soubor s FILES spravci RQ instance, aby nakopiroval soubory na disk Vasi instance RQ
- po potvrzeni od spravce muzete zacit importovat pres Administrace -> Import soubory XML (nejdrive si zip na svem disku rozbalte)
