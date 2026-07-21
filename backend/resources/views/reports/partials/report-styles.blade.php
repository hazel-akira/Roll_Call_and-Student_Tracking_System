@page {
    margin: 18px 20px 36px;
}

* {
    box-sizing: border-box;
}

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 9.5px;
    color: #111827;
    margin: 0;
    line-height: 1.35;
}

.report-header {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
}

.report-header td {
    border: none;
    vertical-align: middle;
    padding: 0;
}

.report-header-logo {
    width: 72px;
}

.report-header-logo-right {
    width: 72px;
}

.school-logo {
    max-width: 64px;
    max-height: 64px;
    object-fit: contain;
}

.report-header-text {
    text-align: center;
}

.report-school-name {
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #0f172a;
    margin: 0 0 2px;
}

.report-doc-title {
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    color: #1e3a5f;
    margin: 0 0 2px;
}

.report-subtitle {
    font-size: 8.5px;
    color: #475569;
    margin: 0;
}

.report-header-rule {
    border-bottom: 2px solid #1e3a5f;
    margin: 6px 0 20px;
}

.meta-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.meta-table td {
    border: 1px solid #cbd5e1;
    padding: 5px 8px;
    vertical-align: top;
    width: 50%;
    background: #f8fafc;
}

.meta-label {
    font-weight: bold;
    color: #334155;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-top: 24px;
}

.data-table th,
.data-table td {
    border: 1px solid #94a3b8;
    padding: 3px 5px;
    vertical-align: middle;
    word-wrap: break-word;
}

.data-table thead th {
    text-align: center;
    font-weight: bold;
    background: #e2e8f0;
    color: #0f172a;
}

.data-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

.summary-table {
    width: 48%;
    border-collapse: collapse;
    margin-top: 64px;
}

.summary-table th,
.summary-table td {
    border: 1px solid #94a3b8;
    padding: 5px 8px;
}

.summary-table th {
    background: #1e3a5f;
    color: #ffffff;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
}

.summary-table .summary-value {
    text-align: center;
    font-weight: bold;
    width: 28%;
    background: #f1f5f9;
}

.report-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: 22px;
    padding-top: 4px;
    border-top: 1px solid #94a3b8;
    font-size: 8px;
    color: #64748b;
    background: #ffffff;
}

.report-footer-table {
    width: 100%;
    border-collapse: collapse;
}

.report-footer-table td {
    border: none;
    padding: 0;
    vertical-align: middle;
}

.footer-left {
    text-align: left;
    font-weight: bold;
    width: 28%;
}

.footer-center {
    text-align: center;
    width: 44%;
}

.footer-right {
    text-align: right;
    width: 28%;
}

.status-cell {
    text-align: center;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 8px;
}

.time-in {
    font-size: 6.5px;
    font-weight: normal;
    font-style: italic;
    color: #475569;
    margin-top: 1px;
    line-height: 1.1;
}

.subhead {
    font-size: 6.5px;
    font-weight: normal;
    color: #64748b;
    line-height: 1.1;
}
