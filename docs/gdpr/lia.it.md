# Valutazione di impatto del legittimo interesse (LIA)

Riferimento: articolo 6, paragrafo 1, lettera f, GDPR — valutazione comparativa
a sostegno della base giuridica del legittimo interesse per il trattamento dei
dati di atleti ed eventi.

| Campo | Valore |
| --- | --- |
| Titolare | Valore di `APP_OWNER` (ambiente di distribuzione), indirizzo `APP_OWNER_ADDRESS`, email `APP_OWNER_EMAIL` |
| Versione del documento | 2026-08-05 |
| Ciclo di revisione | Ogni 12 mesi, o a ogni modifica di finalità, categorie di dati, garanzie o conservazione |
| Stato | Corrente |

## 1. Finalità e ambito

Questa valutazione documenta la comparazione a sostegno del trattamento dei
dati di atleti ed eventi sulla base del legittimo interesse (articolo 6,
paragrafo 1, lettera f, GDPR), come annunciato nell'informativa privacy
pubblica:

- gestione dell'archivio atleti della società (inserimento, modifica,
  importazione, esportazione, riconciliazione);
- iscrizione degli atleti agli eventi, invio dei riepiloghi alle società e
  consolidamento del verbale degli eventi chiusi (copie consolidate);
- pubblicazione di totali aggregati di iscrizioni e medaglie dopo un evento.

Non copre i trattamenti fondati sul contratto (articolo 6, paragrafo 1,
lettera b, GDPR), che l'informativa applica a creazione dell'account, accesso
al servizio e fasi precontrattuali.

## 2. Interessi legittimi perseguiti

1. **Titolare:** gestione del portale per le competizioni, protezione dagli
   abusi ed erogazione dei servizi pubblicati.
2. **Organizzatori:** gestione della partecipazione, dell'ammissibilità,
   delle iscrizioni e delle quote e conservazione di un verbale affidabile
   degli eventi chiusi.
3. **Società partecipanti:** amministrazione del proprio archivio atleti e
   invio delle iscrizioni senza duplicazioni cartacee o manuali.

Il trattamento soddisfa tutti e tre gli interessi; l'interesse del titolare e
degli organizzatori coincide con l'esigenza operativa delle società di gestire
le competizioni.

## 3. Necessità

L'iscrizione di atleti ed eventi non può funzionare senza i minimi attributi
di identità e competitivi utilizzati (nome e cognome, data di nascita, genere,
peso, cintura, eventuale numero di tesseramento e note scelte dalla società).
Nessuna alternativa con impatto sostanzialmente minore consegue lo scopo:

- la superficie pubblica è ridotta ai totali aggregati — nessun risultato
  nominale è pubblicato per scelta progettuale;
- le viste dettagliate degli atleti sono limitate alla società di
  appartenenza e agli amministratori;
- gli eventi non pubblicati rifiutano le richieste di metadati delle iscrizioni;
- il campo libero delle note è scoraggiato per contenuti sensibili.

## 4. Impatto sugli interessati

- **Interessati:** atleti (compresi minori) e referenti delle società.
- **Categorie di dati:** identità, data di nascita, genere, peso, cintura,
  identificativo di tesseramento, recapiti della società, dati di iscrizione
  e istruzioni di pagamento, dati tecnici di sicurezza. Nessuna categoria
  particolare è raccolta per scelta progettuale (l'articolo 9 è
  espressamente escluso nell'informativa e nelle Condizioni).
- **Rischio di danno:** basso. I dati restano nell'ambito delle società
  partecipanti, dell'organizzatore e del titolare; nessun risultato nominale
  pubblico, nessuna profilazione, nessun marketing, nessuna decisione
  automatizzata con effetti giuridici.
- **Minori:** rilievo particolare ai diritti dei minori: nessun risultato
  nominale pubblico, nessuna valutazione individuale, accesso all'archivio
  limitato alla società di appartenenza e garanzia della società di consegna
  dell'informativa ai sensi dell'articolo 14 al genitore o tutore prima di
  fornire i dati.

## 5. Garanzie già in essere

| Ambito | Garanzia |
| --- | --- |
| Accesso | Ambito per società applicato nelle query; vincolo di proprietà composito; accesso amministratore solo alla tabella completa |
| Sicurezza | Prepared statement, CSRF, limitazione delle richieste, sessioni rigorose, CSP con nonce, sandbox per i caricamenti |
| Crescita | Approvazione amministrativa prima dell'attivazione; quote atleti e iscrizioni (`CLUB_ATHLETE_LIMIT`, `CLUB_ENTRY_LIMIT`) |
| Conservazione | Copie consolidate degli eventi chiusi eliminate dopo 1 anno; token 1h/24h; documenti eliminati alla sostituzione o all'eliminazione dell'evento; scadenza log/backup secondo `APP_LOG_RETENTION_DAYS` / `APP_BACKUP_RETENTION_DAYS` |
| Informativa | Informativa privacy pubblica (art. 13/14), accettazione versionata delle Condizioni, garanzia versionata di consegna ai sensi dell'articolo 14 |
| Diritti | Canale di contatto pubblicato; cancellazione ed esportazione lato società; esportazione amministratore prima dell'eliminazione della società |

## 6. Conclusione della comparazione

Gli interessi del titolare, degli organizzatori e delle società nella gestione
e nell'amministrazione delle competizioni sono legittimi e il trattamento è
necessario al loro conseguimento. L'impatto sugli interessati è limitato da
minimizzazione, ambito per società, assenza di qualsiasi risultato nominale
pubblico, conservazione restrittiva e solide garanzie tecniche; per i minori,
l'assenza di risultati nominali pubblici e l'obbligo di consegna
dell'informativa ai sensi dell'articolo 14 pesano in modo decisivo nella
comparazione.

**Conclusione: gli interessi legittimi prevalgono sull'impatto; il
trattamento sulla base dell'articolo 6, paragrafo 1, lettera f, GDPR è
giustificato per l'ambito di cui alla sezione 1.**

## 7. Revisione

Rivalutare al verificarsi di uno qualsiasi dei seguenti eventi: modifica di
finalità o categorie di dati; modifica del comportamento di pubblicazione;
modifica dei termini di conservazione; modifica delle garanzie; modifica della
composizione di destinatari o sub-responsabili; o dopo un incidente di
protezione dei dati. La revisione deve essere registrata nel presente
documento.

Evidenze storiche: audit di giugno 2026, revisione post-riparazione di luglio
2026 e baseline di sicurezza in `docs/security.md`.
