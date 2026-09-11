=== AIsuru ===
Contributors: memori
Tags: artificial intelligence, chatbot, assistant, aisuru
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.2.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Integra gli agenti AIsuru in WordPress con PII detection, lingua automatica e layout ottimizzati.

== Description ==

Il plugin collega WordPress a una Landing Experience AIsuru tramite Integration ID.
Le regole PII, i colori e le altre impostazioni salvate in AIsuru vengono caricate
automaticamente dal widget.

Layout disponibili:

* Website Assistant, tramite shortcode oppure globale.
* Chat.
* Full Page.
* Totem.
* Zoomed Full Body.

La lingua dell'interfaccia è italiana sui siti italiani e inglese negli altri casi.
La lingua dell'agente segue il locale WordPress quando supportato, altrimenti usa
l'inglese.

== Installation ==

1. Carica e attiva il plugin.
2. Apri AIsuru > Aggiungi agente.
3. Copia gli identificativi dalla sezione Condividi di AIsuru.
4. Inserisci lo shortcode `[aisuru id="1"]` in una pagina o in un articolo.

Lo shortcode legacy `[memori id="1"]` resta supportato.

== Upgrade Notice ==

= 2.2.0 =
Aggiunge le opzioni avanzate del widget presenti negli embed completi mantenendo
le configurazioni specifiche degli agenti fuori dal pacchetto del plugin.

= 2.1.0 =
Aggiorna gli asset Memori, aggiunge showLogin e avatar3dHidden e rende più sicura
la conferma della migrazione legacy su installazioni con prefisso personalizzato.

= 2.0.0 =
Le configurazioni del vecchio plugin vengono migrate automaticamente mantenendo
gli ID shortcode. Per abilitare la PII, apri ogni configurazione migrata e aggiungi
l'Integration ID della Landing Experience.

== Changelog ==

= 2.2.0 =
* Supporto per showMessageConsumption e maxTotalMessagePayload.
* Supporto opzionale per tag e PIN persona.
* Sincronizzazione dei relativi valori ereditati dalla Landing Experience.
* Nessun salvataggio della password dell’agente.

= 2.1.0 =
* Web component Memori 8.3.1 e stili Memori React 8.45.2.
* Endpoint hosted predefiniti aggiornati ai percorsi API correnti.
* Supporto per showLogin e avatar3dHidden.
* Validazione canonica degli UUID.
* Loader leggero per shortcode, widget e page builder.
* Conferma amministrativa per tabelle legacy wp_ con prefisso differente.

= 2.0.0 =
* Nuovo pannello amministrativo responsive.
* PII detection tramite Landing Experience.
* Lingua automatica dal locale WordPress.
* Backend ed engine URL configurabili.
* Website Assistant inline o globale.
* Migrazione sicura dal plugin legacy.
