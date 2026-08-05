# Registro dei trattamenti (articolo 30 GDPR)

| Campo | Valore |
| --- | --- |
| Titolare | Valori di `APP_OWNER`, `APP_OWNER_ADDRESS`, `APP_OWNER_EMAIL`, `APP_OWNER_FISCAL_CODE` (ambiente di distribuzione) |
| Versione del documento | 2026-08-05 |
| Ciclo di revisione | Ogni 12 mesi, o a ogni modifica di finalità, destinatari, conservazione o trasferimenti |
| Stato | Corrente; il titolare deve mantenere verificati l'host nominato e i suoi sub-responsabili |

## 1. Attività di trattamento

| # | Attività | Interessati | Categorie di dati personali | Destinatari | Conservazione | Trasferimenti fuori SEE | Misure di sicurezza |
| --- | --- | --- | --- | --- | --- | --- | --- |
| A | Account società e autenticazione (registrazione, approvazione, accesso, recupero) | Referenti delle società | Nome, email, telefono, indirizzo, affiliazione, identificativi fiscali/federali, hash della password, data di approvazione, evidenze di limitazione | Fornitore di hosting del titolare e personale autorizzato; la società stessa | Fino all'eliminazione della società da parte di un amministratore; token di flusso 1h (recupero) / 24h (conferma), eliminati al successo o dal processo giornaliero | Nessuno per scelta progettuale | Prepared statement, CSRF, limitazione delle richieste, sessioni rigorose, politica delle password |
| B | Gestione archivio atleti (inserimento, modifica, importazione, esportazione, riconciliazione) | Atleti, compresi minori | Nome e cognome, data di nascita, genere, peso, cintura, numero di tesseramento, note in campo libero, file di importazione/esportazione | Società di appartenenza; amministratori; fornitore di hosting; destinatari CSV scelti dalla società | Fino all'eliminazione dell'atleta da parte della società o di un amministratore; le copie consolidate seguono la riga D | Nessuno per scelta progettuale | Ambito per società, vincoli di proprietà, quote, politiche sui file |
| C | Pubblicazione eventi e iscrizioni (iscrizioni, opzioni, quote, riepiloghi) | Atleti, società | Dati di iscrizione, opzioni e quote selezionate, eccezioni evento, email di riepilogo | Organizzatore/amministratori, società di appartenenza, fornitore di hosting/mail | Fino all'eliminazione dell'evento; le copie consolidate seguono la riga D | Nessuno per scelta progettuale | Gate di autorizzazione, CSRF, prepared statement, FK di proprietà delle iscrizioni |
| D | Consolidamento eventi chiusi (copie consolidate) e statistiche aggregate | Atleti, società | Copia congelata dei dati di iscrizione e delle categorie; totali aggregati per società | Amministratori; pubblico (solo totali aggregati) | 1 anno dalla copia o dall'evento, poi eliminazione dal processo giornaliero | Nessuno per scelta progettuale | Nessun risultato nominale pubblico, purge indicizzata, ambito per società |
| E | Gestione istruzioni di pagamento | Organizzatore | Intestatario, IBAN e BIC SEPA dell'organizzatore | Società partecipanti; fornitore di hosting | Fino all'eliminazione dell'evento | Nessuno per scelta progettuale | Visualizzazione solo nel contesto dell'evento |
| F | Documenti degli eventi | Atleti, società | Documenti PDF/JPEG/PNG forniti dagli organizzatori | Visitatori pubblici (eventi pubblicati), fornitore di hosting | Eliminati alla sostituzione o all'eliminazione dell'evento | Nessuno per scelta progettuale | Limiti MIME/dimensione, nomi generati, directory sandbox, download forzato per i PDF |
| G | Dati tecnici e di sicurezza | Visitatori, società, amministratori | Identificativi di sessione, evidenze di limitazione, log degli errori applicativi, ID di correlazione | Fornitore di hosting; operatori autorizzati | Sessioni 30 min di inattività / 12 h assolute; log secondo `APP_LOG_RETENTION_DAYS` (rotazione gestita dall'host) | Nessuno per scelta progettuale | Percorso di errore con redazione, ID di correlazione per richiesta, CSP con nonce |
| H | Hosting e backup | Tutti gli interessati sopra | Copia completa del database applicativo e dei caricamenti | Fornitore di hosting (`APP_WEBHOST`, attualmente Aruba Linux Basic) e suoi sub-responsabili | Backup secondo `APP_BACKUP_RETENTION_DAYS` (scadenza gestita dall'host) | Nessuno per scelta progettuale; sub-responsabili e garanzie da verificare da parte del titolare | Controlli di accesso dell'host, trasporto cifrato, test di ripristino secondo runbook di distribuzione |

## 2. Basi giuridiche utilizzate

- Articolo 6, paragrafo 1, lettera b: creazione dell'account, accesso al
  servizio, fasi precontrattuali.
- Articolo 6, paragrafo 1, lettera f: trattamento di atleti ed eventi — cfr.
  [Valutazione di impatto del legittimo interesse](lia.it.md).
- Non si fa affidamento sul consenso; la garanzia di consegna ai sensi
  dell'articolo 14 registrata per ciascuna società non è il consenso
  dell'atleta.

## 3. Note e obblighi

- Nessuna categoria particolare (articolo 9) è raccolta per scelta
  progettuale; i campi liberi sono scoraggiati per contenuti sensibili.
- Nessuna decisione automatizzata con effetti giuridici; le categorie di età
  e peso sono esclusivamente calcoli amministrativi.
- L'informativa privacy in `/privacy` riflette il presente registro; il
  titolare deve mantenere allineati informativa e registro e verificare i
  sub-responsabili dell'host e le eventuali garanzie di trasferimento future
  (cfr. `docs/deployment.md`).
