# 🏛️ AUREUS - Plataforma de Subastas de Arte y Mecenazgo Digital

**AUREUS** es un Proyecto Intermodular desarrollado para el CFGS de Desarrollo de Aplicaciones Web (2º DAW). Inspirada en el mecenazgo romano y bajo una estética _Dark Luxury_, la plataforma permite a mecenas y artistas interactuar en un mercado de subastas de arte digital y clásico en tiempo real, garantizando transacciones seguras mediante contratos de custodia (_Escrow_).

## 👥 Equipo de Desarrollo

- **Alvarus** - Frontend, UX/UI, Arquitectura SPA (Single Page Application) e Integración de SDK Bancario (PayPal).
- **Robertus** - Backend, Arquitectura de Datos, Sistema RBAC y Transacciones ACID (MySQL).

## 🛠️ Stack Tecnológico y Arquitectura

El proyecto se fundamenta en una arquitectura estricta **MVC (Modelo-Vista-Controlador)** sin dependencias de frameworks pesados, optimizando el rendimiento y la seguridad:

- **Frontend (Capa de Presentación):** HTML5 Semántico, CSS3 (Custom Properties), JavaScript (ES6+ Modules).
  - _Componentes:_ `Chart.js` (análisis financiero), `Tabulator` (Ledger y auditoría de bóveda), `EasyTimer.js` (sincronización temporal) y `noUiSlider`.
  - _Enfoque:_ SPA impulsada por Fetch API. Mantenimiento de estado global y enrutamiento persistente en navegador.
- **Backend (Capa de Lógica de Negocio):** PHP 8+ (Orientado a Objetos).
  - _Enrutamiento:_ Implementación del patrón de diseño **Front Controller** (`index.php`) para centralizar las peticiones API REST.
  - _Rendimiento:_ Algoritmos de **Caché Estático (File Caching)** para el _Ticker_ de actividad global, mitigando el estrés del servidor ante peticiones concurrentes (_Long Polling_).
- **Base de Datos (Capa de Persistencia):** MariaDB / MySQL.
  - _Concurrencia:_ Uso exclusivo de Consultas Preparadas y Bloqueos Pesimistas (`SELECT ... FOR UPDATE`) dentro de transacciones ACID para evitar _Race Conditions_ en los saldos.
  - _Reglas de Negocio:_ Incremento mínimo forzado de 50€ en licitaciones y sistema anti-sniping dinámico.
- **Servicios Auxiliares:** Python (Microservicio de Data Analytics y cuadros de mando administrativos).
