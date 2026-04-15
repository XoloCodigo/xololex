# XoloLex

Bot de WhatsApp (WAHA) para abogados que automatiza la generación de reportes de visita y el llenado de formularios en SharePoint.

## Stack
- Laravel 13 + Filament 5 (panel admin)
- WAHA (WhatsApp HTTP API) para el bot
- PhpWord + DomPDF para generación de documentos
- Microsoft Graph API para SharePoint (archivos + listas)
- PostgreSQL (producción) / SQLite (desarrollo)

## Estructura del proyecto

### Modelos principales
- `Lawyer` — Abogados registrados (identificados por teléfono)
- `Company` — Empresas que se visitan
- `Report` — Reportes de visita generados
- `SharepointForm` — Formularios de seguimiento (1:1 con Report)
- `Conversation` — Estado de la conversación activa del bot (máquina de estados)

### Servicios
- `WahaService` — Envío de mensajes WhatsApp via WAHA
- `Bot/BotHandler` — Router principal del bot (menú + dispatch a flujos)
- `Bot/ReportFlow` — Flujo conversacional para crear reportes
- `Bot/FormFlow` — Flujo conversacional para llenar formularios
- `ReportGeneratorService` — Genera Word y PDF desde datos del reporte

### Flujo del bot
1. Abogado escribe al bot → menú: Reporte / Formulario / Ambos
2. Reporte: preguntas guiadas → genera Word + PDF → envía PDF por WhatsApp
3. Formulario: preguntas guiadas → inserta en SharePoint List
4. "Ambos": hace reporte y luego formulario en secuencia

### Webhooks
- `POST /webhook/waha` — Recibe mensajes de WAHA (sin CSRF)

## Variables de entorno requeridas
```
WAHA_URL=http://localhost:3000
WAHA_SESSION=default
MS_TENANT_ID=
MS_CLIENT_ID=
MS_CLIENT_SECRET=
MS_SHAREPOINT_SITE_ID=
MS_SHAREPOINT_DRIVE_ID=
MS_SHAREPOINT_LIST_ID=
```

## Convenciones
- Folios de reportes: RL-0001, RL-0002...
- Conversación: máquina de estados en BD (flow + step + data JSON)
- El bot solo responde a abogados registrados y activos
- "cancelar" o "0" resetea la conversación en cualquier punto

## Deploy
- Servidor: xolobots (Hetzner) via Ploi
- Base de datos: PostgreSQL
- Mismo servidor que XoloDocs y XoloSoporte

---

## Roadmap

### Fase 0 — Setup [COMPLETADA]
- [x] Proyecto Laravel + dependencias
- [x] Modelos y migraciones
- [x] WAHA webhook handler
- [x] Máquina de estados del bot
- [x] Flujos: ReportFlow + FormFlow
- [x] Generación Word/PDF
- [ ] Panel admin Filament (pendiente)

### Fase 1 — Bot funcional (demo)
- [ ] Conectar WAHA al webhook
- [ ] Probar flujo completo de reporte
- [ ] Probar flujo completo de formulario
- [ ] Probar opción "ambos"
- [ ] Ajustar plantilla Word según feedback del cliente

### Fase 2 — Integración SharePoint
- [ ] OAuth2 con Microsoft Graph API
- [ ] Subir PDF a SharePoint Drive
- [ ] Insertar datos en SharePoint List
- [ ] Confirmación al abogado con links

### Fase 3 — Panel admin
- [ ] CRUD de abogados
- [ ] CRUD de empresas
- [ ] Lista de reportes generados (con descarga)
- [ ] Lista de formularios enviados
- [ ] Historial de conversaciones
- [ ] Dashboard: reportes por abogado, por empresa, por fecha

### Fase 4 — Refinamiento (post-demo)
- [ ] Fotos adjuntas al reporte
- [ ] Edición de respuestas antes de generar
- [ ] Múltiples visitas en un día (flujo encadenado)
- [ ] Notificaciones al coordinador
- [ ] Plantillas configurables desde el admin
