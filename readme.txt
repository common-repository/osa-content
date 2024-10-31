=== OSA Content ===
Contributors: osacas
Tested up to: 5.4
Stable tag: 0.5
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publikuj na stronie WordPress zawartość Twojego archiwum pobraną z osa.archiwa.org. UWAGA! deinstalacja wtyczki oznacza usunięcie metadanych załadowanych z OSA. Pliki załączników załadowanych z OSA należy usunąć ręcznie.

== Description ==

Standardowa wtyczka WordPress. W przypadku instalacji ręcznej należy ją umieścić w katalogu wp-content/plugins/. Przed użyciem wtyczkę należy aktywować w panelu administracyjnym.

Wtyczka tworzy 4 typy postów: Zbiór, Serię, Jednostkę i Dokument co odpowiada strukturze archiwum ISAD(G) oraz strukturze w OSA i pobiera udostępnione zbiory wybranej instytucji z OSA. Posty wzbogacone są o metadane takie jak m.in. data, indeksy, autorzy itd.

W panelu administracyjnym w menu bocznym w sekcji Narzędzia/Wtyczka OSA znajdują się podstawowe opcje konfiguracyjne wtyczki oraz możliwość zaciągnięcia danych z OSA:

- Adres URL OSA: pole obowiązkowe, należy tutaj wprowadzić adres instancji OSA, z której chcemy pobrać dane. Najlepiej wpisać tutaj https://osa.archiwa.org
- Id instytucji: również pole obowiązkowe, należy podać tutaj ID instytucji, której dane chcemy pobrać, np: PL_1013
- Zakres pobierania: możliwość wyboru opcji “wszystkie zbiory instytucji” lub “wybrane zbiory”
- Id zbiorów do pobrania (pole jest aktywne w przypadku zaznaczenia opcji “wybrane zbiory”): należy tutaj wpisać ID wybranych zbiorów instytucji oddzielone przecinkami
- Checkbox "Użyj przykładowego szablonu" na razie zostawiamy zaznaczony, poniżej więcej informacji na ten temat.

Aby pobrać dane instytucji z OSA, należy najpierw zapisać ustawienia, a potem wcisnąć przycisk "Pobierz dane z OSA". Rozpocznie to proces, który w zależności od wielkości kolekcji może trwać od kilku do kilkudziesięciu minut. Aby podejrzeć postęp procesu należy odświeżać co jakiś czas stronę. Załączniki do dokumentów lub jednostek zostaną również pobrane, będą dołączone do dokumentów i jednostek jako elementy potomne (children). Można je będzie przejrzeć w sekcji "Media".

Zgodnie ze standardem ISAD archiwum ma strukturę hierarchiczną: zbiory zawierają serie lub jednostki, serie mają podserie lub jednostki, a jednostki zawierają dokumenty. Domyślny sposób wyświetlania archiwum przez wtyczkę zachowuje tę strukturę. W widoku pojedynczego zbioru lub serii widzimy listę należących do niego serii i jednostek, a w jednostkach listę dokumentów. Poza tym widok pojedynczego elementu archiwum prezentuje jego metadane i załączniki.

Domyślny szablon pojedynczego zbioru/serii/jednostki/dokumentu służy bardziej prezentacji możliwości wtyczki niż jest docelowym rozwiązaniem, które każdy twórca strony korzystającej z wtyczki może zaimplementować samodzielnie korzystając ze skórki WordPressa. Plik szablonu można znaleźć w katalogu wtyczki osa-content/theme/single.php, w pliku functions.php zawarto funkcje renderujące poszczególne metadane.

Sposób prezentacji danych można dostosować we własnej skórce strony. Wystarczy odznaczyć w ustawieniach wtyczki OSA checkbox "Użyj przykładowego szablonu". Plik signle.php z katalogu wtyczki nie będzie wtedy ładowany i samemu trzeba zdefiniować wyświetlanie metadanych w skórce strony, można przy tym używać funkcji zdefiniowanych w pluginie. Jeśli jednak nie chcemy implementować własnego szablonu wyświetlania metadanych w skórce, można po prostu wykorzystać style CSS, aby dostosować wygląd prezentacji metadanych do własnych potrzeb.