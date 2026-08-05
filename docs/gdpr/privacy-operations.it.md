# Operazioni privacy: richieste degli interessati e gestione dei data breach

Riferimento: articoli 12–17, 33, 34 GDPR. Titolare: valore di `APP_OWNER`; il
canale di contatto pubblico è l'email privacy (`APP_OWNER_EMAIL`), pubblicata
nell'informativa in `/privacy`.

| Versione del documento | 2026-08-05 |
| --- | --- |
| Ciclo di revisione | Ogni 12 mesi, o dopo ogni modifica sostanziale dei trattamenti o un incidente |

## 1. Procedura per le richieste degli interessati

### 1.1 Ricezione

- Le richieste arrivano tramite l'email privacy pubblicata (o altri canali
  del titolare).
- Accusare immediatamente ricevuta e registrare la richiesta nel registro
  interno: data, canale, evidenze di identità, diritto richiesto e interessati
  coinvolti (inclusa l'eventualità che si tratti di minori).

### 1.2 Verifica dell'identità

- Verificare l'identità del richiedente prima di agire. Per gli atleti,
  instradare la richiesta tramite la società di appartenenza quando
  l'identità non può essere accertata direttamente, senza rivelare i dati
  dell'atleta a terzi.
- Per le richieste relative a minori, richiedere l'autorità del genitore o
  del tutore.

### 1.3 Gestione per diritto

| Diritto | Gestione nel portale |
| --- | --- |
| Accesso (art. 15) | Fornire una copia completa: esportazione CSV self-service dei dati atleti; esportazione dell'amministratore prima dell'eliminazione della società; in alternativa, estrarre e fornire i record dal database. |
| Rettifica (art. 16) | Modifica atleta lato società, modifica in riga, aggiornamento/riconciliazione CSV; modifica di eventi e società da parte degli amministratori. |
| Cancellazione (art. 17) | La società o un amministratore elimina l'atleta (con cascata di iscrizioni e copie consolidate); l'amministratore elimina la società dopo aver offerto un'esportazione. I verbali consolidati degli eventi chiusi seguono la conservazione documentata di un anno, salvo che si applichi la cancellazione. |
| Limitazione (art. 18) | Non supportata in-app: limitare sospendendo l'account della società e documentando la limitazione, in attesa della risoluzione. |
| Portabilità (art. 20) | L'esportazione CSV self-service della società (AthleteCsvTransfer) copre i dati forniti dalla società. |
| Opposizione (art. 21) | Trattamenti basati sul legittimo interesse: valutare secondo la LIA; registrare l'esito e l'eventuale limitazione applicata. |

### 1.4 Termini e registrazioni

- Rispondere entro un mese dalla verifica dell'identità (art. 12, par. 3);
  proroga di ulteriori due mesi solo se giustificata e comunicata.
- Mantenere aggiornato il registro delle richieste; ogni rifiuto deve indicare
  i motivi e informare sulla possibilità di reclamo (Garante per la
  protezione dei dati personali).

## 2. Procedura di gestione dei data breach

### 2.1 Rilevamento e contenimento

- Canali di rilevamento: log degli errori applicativi con ID di correlazione,
  stato di uscita del job di purge, monitoraggio/health check, segnalazioni
  del fornitore di hosting, segnalazioni di utenti e società.
- Contenimento: sospendere gli account coinvolti (approvazione/revoca
  amministrativa), ruotare le credenziali, portare offline l'ambiente se
  necessario, conservare le evidenze (log, backup, stato del database) senza
  alterarle.

### 2.2 Valutazione

- Stabilire le categorie di dati e di interessati coinvolti (compresi i
  minori), le probabili conseguenze (riservatezza, integrità, disponibilità)
  e l'effettiva probabilità/gravità del rischio, considerando le garanzie in
  essere (password hashatate, ambito per società, conservazione).
- Documentare la valutazione nel registro degli incidenti.

### 2.3 Notifica

- Notificare al Garante per la protezione dei dati personali entro 72 ore se
  il breach comporta probabilmente un rischio per i diritti e le libertà,
  con gli elementi dell'art. 33, par. 3 (natura, categorie e numeri
  approssimativi, misure adottate, recapiti).
- Comunicare agli interessati senza ingiustificato ritardo se il breach
  comporta probabilmente un rischio elevato (art. 34), utilizzando i canali
  delle società quando i recapiti degli atleti sono detenuti dalle società.
- Registrare ogni notifica, la sua data, l'autorità e l'esito.

### 2.4 Azioni successive

- Identificare la causa principale, attuare i miglioramenti (incluso, ove
  pertinente, il processo di riparazione del repository) e registrare una
  revisione post-incidente.
- Verificare che le evidenze di log e backup per il periodo siano conservate
  secondo `APP_LOG_RETENTION_DAYS` / `APP_BACKUP_RETENTION_DAYS`.

## 3. Log ed evidenze

- I registri interni delle richieste e degli incidenti sono documentazione
  operativa del titolare; tenerli fuori dai log pubblici e applicare la
  conservazione che il titolare sceglie per la documentazione operativa.
- I test e la documentazione del repository non possono provare il
  comportamento dell'hosting reale: il titolare deve registrare i controlli
  periodici di ripristino, scadenza e scheduler descritti in
  `docs/deployment.md` e `docs/security.md`.
