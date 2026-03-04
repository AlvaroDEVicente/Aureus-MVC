# 🏛️ AUREUS - Plataforma de Subastas de Arte y Mecenazgo Digital

**AUREUS** es un Proyecto Intermodular desarrollado para el CFGS de Desarrollo de Aplicaciones Web (2º DAW). Inspirada en el mecenazgo romano y bajo una estética *Dark Luxury*, la plataforma permite a mecenas y artistas interactuar en un mercado de subastas de arte (focalizado en la pintura) en tiempo real, garantizando la máxima seguridad financiera.

---

## 👥 Equipo de Desarrollo ("El Senado")
* **Alvarus** - Frontend, UX/UI, Arquitectura SPA y Experiencia del Mecenas.
* **Robertus** - Backend, Arquitectura de Datos, Transacciones ACID y Microservicio Analítico.

---

## ✨ Características Principales (Core Features)

* **⚖️ Sistema Escrow (Custodia de Fondos):** Los fondos del comprador se retienen de forma segura hasta que confirma la recepción física de la obra, momento en el que se liberan al artista.
* **💸 Modelo de Comisiones Realista (Split):** Réplica matemática del sistema de las grandes casas de subastas. Se aplica una **Prima del Comprador (12%)** y una **Comisión del Vendedor (8%)**, generando un beneficio limpio del 20% para la plataforma.
* **🛡️ Prevención de Sniping:** Reglas automáticas que extienden la vida de la subasta en 5 minutos si se detecta una puja de "francotirador" en los últimos instantes.
* **🔮 Oráculo Analítico:** Un microservicio independiente que calcula KPIs económicos y de comunidad en tiempo real para el panel de Administración.
* **👤 Expediente Imperial:** Perfiles de usuario dinámicos con gráficas de distribución de capital (Chart.js) y edición de biografía.

---

## 🛠️ Stack Tecnológico y Arquitectura

El proyecto se fundamenta en una arquitectura distribuida que combina un monolito modular **MVC (Modelo-Vista-Controlador)** con un **Microservicio API RESTful**.

### 💻 Frontend (Cliente - Interfaz SPA)
* **Tecnologías:** HTML5, CSS3 (Custom Properties), JavaScript Vanilla (ES6+).
* **Librerías UX/UI:**
  * **Chart.js:** Gráficas de evolución de valor y distribución de carteras.
  * **Tabulator:** Tablas dinámicas para los registros de la bóveda.
  * **noUiSlider:** Filtro interactivo de presupuestos en el catálogo.
  * **EasyTimer.js:** Sincronización precisa de los relojes de subasta.
  * **SweetAlert2:** Alertas inmersivas y modales de confirmación.
  * **PayPal JS SDK:** Pasarela de simulación de recarga de fondos.
* **Enfoque:** Single Page Application (SPA) gestionada mediante Fetch API. Renderizado dinámico y lógica UX adaptativa sin recargas de página.

### ⚙️ Backend Principal (Servidor PHP)
* **Tecnologías:** PHP puro (Sin frameworks pesados).
* **Enrutamiento:** Patrón Front Controller (`index.php`).
* **Rendimiento:** Sistema de **Caché de Archivos (File Caching)** para el *Ticker* global de pujas, reduciendo el consumo de CPU del servidor frente a peticiones concurrentes (polling).

### 📊 Microservicio OLAP (Python)
* **Tecnologías:** Python 3, FastAPI, Uvicorn, SQLAlchemy.
* **Propósito:** Actúa como un motor de procesamiento analítico en línea (OLAP), descargando al servidor PHP de consultas complejas y sirviendo los datos directamente al Dashboard del Senado.

### 🗄️ Base de Datos (MySQL / MariaDB)
* **Concurrencia y Seguridad:** Uso intensivo de Consultas Preparadas y **Bloqueos Pesimistas** (`SELECT ... FOR UPDATE` en transacciones ACID) para evitar *Race Conditions*, doble gasto o saldos fantasma en los momentos de alta concurrencia.
