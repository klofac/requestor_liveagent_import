# Requestor LiveAgent import
Requirements: PHP7.4

!!! POZOR !!! 
Nez zacnete importovat do RQ musite spravci Vasi instance predat zip soubor s exportem priloh

Pouziti:
- nastavit config.php
- v index.php opravit logiku a nazvy Vasich service front
- spustit localhost?from=0&to=100 (provede export prvnich 100 ticketu)
- vytvori soubor export_from0_to100.xml
- vytvori slozku Import se soubory priloh exportovanych ticketu 
- spoustet opakovane dle uvazeni dalsi bloky from=101&to=999 (vzdy vyexportuje pozadovany rozsah ticketu do jednoho xml souboru a do Import adresare prilohy)
- pokud jiz mate vyexportovano vse, zabalit slozku Import do jednoho zip souboru
- predat zip soubor spravci RQ instance, aby nakopiroval 
- po potvrzeni od spravce muzete zacit importovat pres Administrace -> Import
