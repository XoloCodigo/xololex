<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2563eb; padding-bottom: 15px; }
        .header h1 { font-size: 20px; margin: 0; color: #1e3a8a; }
        .header .folio { font-size: 11px; color: #666; margin-top: 5px; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; padding: 4px 10px 4px 0; width: 160px; color: #555; }
        .info-value { display: table-cell; padding: 4px 0; }
        .section { margin-bottom: 18px; }
        .section h2 { font-size: 13px; color: #1e3a8a; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; }
        .section p { margin: 0; line-height: 1.6; }
        .footer { margin-top: 50px; text-align: center; }
        .footer .line { border-top: 1px solid #333; width: 200px; margin: 0 auto; }
        .footer .name { margin-top: 5px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE VISITA</h1>
        <div class="folio">Folio: {{ $report->folio }} | Fecha: {{ $report->visit_date->format('d/m/Y') }}</div>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Abogado:</div>
            <div class="info-value">{{ $report->lawyer->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Empresa visitada:</div>
            <div class="info-value">{{ $report->company->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contacto:</div>
            <div class="info-value">{{ $report->contact_met }} — {{ $report->contact_position }}</div>
        </div>
    </div>

    <div class="section">
        <h2>Motivo de la visita</h2>
        <p>{{ $report->visit_reason }}</p>
    </div>

    <div class="section">
        <h2>Hallazgos principales</h2>
        <p>{{ $report->findings }}</p>
    </div>

    <div class="section">
        <h2>Riesgos detectados</h2>
        <p>{{ $report->risks ?? 'Ninguno' }}</p>
    </div>

    <div class="section">
        <h2>Recomendaciones</h2>
        <p>{{ $report->recommendations ?? 'N/A' }}</p>
    </div>

    @if ($report->observations && strtolower($report->observations) !== 'ninguna')
        <div class="section">
            <h2>Observaciones adicionales</h2>
            <p>{{ $report->observations }}</p>
        </div>
    @endif

    <div class="footer">
        <div class="line"></div>
        <div class="name">{{ $report->lawyer->name }}</div>
    </div>
</body>
</html>
