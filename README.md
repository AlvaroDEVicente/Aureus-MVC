# 🏛️ AUREUS - Plataforma de Subastas de Arte y Mecenazgo Digital

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-FastAPI-3776AB?style=for-the-badge&logo=python&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-ACID_Transactions-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6_Modules-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

**AUREUS** es un Proyecto Intermodular desarrollado para el CFGS de Desarrollo de Aplicaciones Web (2º DAW). Inspirada en el mecenazgo romano y bajo una estética *Dark Luxury*, la plataforma permite a mecenas y artistas interactuar en un mercado de subastas de arte digital y clásico en tiempo real, garantizando transacciones seguras mediante contratos de custodia (*Escrow*).

## 👥 Equipo de Desarrollo

- **Alvarus** - Frontend, UX/UI, Arquitectura SPA (Single Page Application) e Integración de SDK Bancario (PayPal).
- **Robertus** - Backend, Arquitectura de Datos, Sistema RBAC y Transacciones ACID (MySQL).

## 🛠️ Stack Tecnológico y Arquitectura

El proyecto se fundamenta en una arquitectura estricta **MVC (Modelo-Vista-Controlador)** sin dependencias de frameworks pesados, optimizando el rendimiento y la seguridad:

- **Frontend (Capa de Presentación):** HTML5 Semántico, CSS3 (Custom Properties), JavaScript (ES6+ Modules).
  - *Componentes:* `Chart.js` (análisis financiero), `Tabulator` (Ledger y auditoría de bóveda), `EasyTimer.js` (sincronización temporal) y `noUiSlider`.
  - *Enfoque:* SPA impulsada por Fetch API. Mantenimiento de estado global y enrutamiento persistente en navegador.
- **Backend (Capa de Lógica de Negocio):** PHP 8+ (Orientado a Objetos).
  - *Enrutamiento:* Implementación del patrón de diseño **Front Controller** (`index.php`) para centralizar las peticiones API REST.
  - *Rendimiento:* Algoritmos de **Caché Estático (File Caching)** para el *Ticker* de actividad global, mitigando el estrés del servidor ante peticiones concurrentes (*Long Polling*).
- **Base de Datos (Capa de Persistencia):** MariaDB / MySQL.
  - *Concurrencia:* Uso exclusivo de Consultas Preparadas y Bloqueos Pesimistas (`SELECT ... FOR UPDATE`) dentro de transacciones ACID para evitar *Race Conditions* en los saldos.
  - *Reglas de Negocio:* Incremento mínimo forzado de 50€ en licitaciones y sistema anti-sniping dinámico.
- **Servicios Auxiliares:** Python (Microservicio de Data Analytics y cuadros de mando administrativos).

## ✨ Características Destacadas

* **Sistema Financiero Escrow (Partida Doble):** Motor contable que retiene fondos durante las pujas, cobra comisiones de plataforma de forma automatizada y genera un Libro Mayor auditable para compradores y vendedores.
* **Seguridad y Mitigación de Vulnerabilidades:** Validación exhaustiva de tipos MIME para subida de archivos (prevención RCE), sanitización de inputs y control de acceso basado en roles.
* **Dashboard Administrativo (Modo Dios):** Panel de control (Senado) para la aprobación de obras, inyección de fondos, manipulación temporal de subastas y amnistía de usuarios.

---
*AUREUS - El valor de lo eterno.*
