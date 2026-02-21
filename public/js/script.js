/**
 * AUREUS - Controlador Principal del Cliente (Frontend SPA)
 * Autores: Alvarus (Frontend) / Robertus (Backend)
 * Versión: Adaptada a Front Controller (MVC)
 */

const RUTA_API = "../index.php?accion=";

let allArtworks = [];
let activeTimers = [];
let detailTimer = null;
let priceChart = null;
let globalTickerTable = null;
let pollingInterval = null;

document.addEventListener("DOMContentLoaded", () => {
  loadUserData();
  loadCatalog();
  setupBidForm();
  initGlobalTicker();
});

// ============================================================================
// 1. CARGA DE ESTADO Y ENRUTAMIENTO POR ROL
// ============================================================================
async function loadUserData() {
  try {
    const response = await fetch(RUTA_API + "obtener_usuario");
    const user = await response.json();

    if (user.error) {
      window.location.href = "login.php";
      return;
    }

    document.getElementById("nav-username").innerText = user.nombre;
    document.getElementById("nav-saldo-disponible").innerText = Number(
      user.saldo_disponible,
    ).toFixed(2);
    document.getElementById("nav-saldo-bloqueado").innerText = Number(
      user.saldo_bloqueado,
    ).toFixed(2);

    // --- MODIFICACIÓN: Lógica de menús independizada ---

    // 1. La bóveda se muestra por defecto para gestionar el saldo (compradores y artistas)
    document.getElementById("nav-vault-btn").style.display = "inline-block";

    // 2. El taller se suma si el usuario tiene el flag de artista
    if (user.es_artista == 1) {
      document.getElementById("nav-workshop-btn").style.display =
        "inline-block";
    }

    // 3. La mesa del senado se muestra solo para los administradores
    if (user.rol === "admin") {
      document.getElementById("nav-admin-btn").style.display = "inline-block";
      // Ocultamos la bóveda al admin ya que su rol es solo supervisar
      document.getElementById("nav-vault-btn").style.display = "none";
    }
    // ---------------------------------------------------
  } catch (error) {
    window.location.href = "login.php";
  }
}

// ----------------------------------------------------------------------------
// Funciones del Enrutador SPA
// ----------------------------------------------------------------------------
function hideAllViews() {
  document.getElementById("view-catalog").style.display = "none";
  document.getElementById("view-detail").style.display = "none";
  document.getElementById("view-workshop").style.display = "none";
  document.getElementById("view-vault").style.display = "none";
  document.getElementById("view-admin").style.display = "none";
  if (detailTimer) detailTimer.stop();
}

function deactivateAllNavs() {
  document
    .querySelectorAll(".nav-link")
    .forEach((link) => link.classList.remove("active"));
}

window.showCatalog = function () {
  hideAllViews();
  deactivateAllNavs();
  document.getElementById("view-catalog").style.display = "block";
  document.getElementById("nav-catalog-btn").classList.add("active");
  loadCatalog();
};

window.showWorkshop = function () {
  hideAllViews();
  deactivateAllNavs();
  document.getElementById("view-workshop").style.display = "block";
  document.getElementById("nav-workshop-btn").classList.add("active");
  loadWorkshopData();
};

window.showVault = function () {
  hideAllViews();
  deactivateAllNavs();
  document.getElementById("view-vault").style.display = "block";
  document.getElementById("nav-vault-btn").classList.add("active");
  loadVaultData();
};

window.showAdmin = function () {
  hideAllViews();
  deactivateAllNavs();
  document.getElementById("view-admin").style.display = "block";
  document.getElementById("nav-admin-btn").classList.add("active");
  loadAdminData();
};

// ============================================================================
// 2. CATÁLOGO Y FILTROS (noUiSlider)
// ============================================================================
async function loadCatalog() {
  try {
    const response = await fetch(RUTA_API + "obtener_catalogo");
    allArtworks = await response.json();

    initPriceSlider();
    renderCatalog(allArtworks);
  } catch (error) {
    console.error("Error cargando el catálogo:", error);
  }
}

function initPriceSlider() {
  const slider = document.getElementById("slider-precio");
  if (!slider) return;
  if (slider.noUiSlider) slider.noUiSlider.destroy();

  let maxPrice =
    allArtworks.length > 0
      ? Math.max(...allArtworks.map((a) => Number(a.precio_actual)))
      : 1000;
  maxPrice = Math.ceil(maxPrice / 100) * 100;

  noUiSlider.create(slider, {
    start: [0, maxPrice],
    connect: true,
    step: 10,
    range: { min: 0, max: maxPrice },
  });

  slider.noUiSlider.on("update", (values) => {
    const min = parseInt(values[0]);
    const max = parseInt(values[1]);
    document.getElementById("precio-min-label").innerText = `${min} €`;
    document.getElementById("precio-max-label").innerText = `${max} €`;

    const filtered = allArtworks.filter(
      (art) =>
        Number(art.precio_actual) >= min && Number(art.precio_actual) <= max,
    );
    document.getElementById("filtro-contador").innerText =
      `${filtered.length} lotes encontrados`;
    renderCatalog(filtered);
  });
}

function renderCatalog(artworksToRender) {
  const container = document.getElementById("catalog-container");
  const template = document.getElementById("artwork-card-template");

  activeTimers.forEach((t) => t.stop());
  activeTimers = [];
  container.innerHTML = "";

  artworksToRender.forEach((art) => {
    const clone = template.content.cloneNode(true);
    const cardArticle = clone.querySelector("article");

    clone.querySelector(".card-title").innerText = art.titulo;
    clone.querySelector(".card-image").src = art.imagen_url;
    clone.querySelector(".card-precio-actual").innerText = Number(
      art.precio_actual,
    ).toFixed(2);

    const btn = clone.querySelector(".card-btn");
    btn.dataset.id = art.id_obra;
    btn.addEventListener("click", () => openArtworkDetail(art.id_obra));

    const timerDisplay = clone.querySelector(".countdown-display");
    const secondsLeft = calculateSecondsLeft(art.fecha_fin);

    if (secondsLeft > 0) {
      const timer = new Timer();
      timer.start({ countdown: true, startValues: { seconds: secondsLeft } });
      timer.addEventListener("secondsUpdated", () => {
        let dias = timer.getTimeValues().days;
        let horas = timer.getTimeValues().hours;
        let minutos = timer.getTimeValues().minutes;
        let segundos = timer.getTimeValues().seconds;

        // Si falta más de 1 día, mostramos el texto fácil: "X días y Y horas"
        if (dias > 0) {
          timerDisplay.innerText = dias + " días, " + horas + " horas";
        } else {
          // Si falta menos de un día, mostramos el reloj normal (HH:MM:SS)
          timerDisplay.innerText = timer.getTimeValues().toString();
        }
      });
      timer.addEventListener("targetAchieved", () => {
        timerDisplay.innerText = "FINALIZADA";
        timerDisplay.classList.replace("text-gold", "text-danger");
        btn.disabled = true;
        cardArticle.classList.add("terminado");
      });
      activeTimers.push(timer);
    } else {
      timerDisplay.innerText = "FINALIZADA";
      timerDisplay.classList.replace("text-gold", "text-danger");
      btn.disabled = true;
      cardArticle.classList.add("terminado");
    }
    container.appendChild(clone);
  });
}

// ============================================================================
// 3. FICHA DETALLADA, GRÁFICAS Y TABLAS
// ============================================================================
async function openArtworkDetail(id_obra) {
  try {
    const response = await fetch(`${RUTA_API}obtener_detalle&id=${id_obra}`);
    const data = await response.json();

    if (data.error) return alert(data.error);

    hideAllViews();
    document.getElementById("view-detail").style.display = "block";
    deactivateAllNavs();

    document.getElementById("detail-title").innerText = data.titulo;
    document.getElementById("detail-image").src = data.imagen_url;
    document.getElementById("detail-desc").innerText = data.descripcion;
    document.getElementById("detail-bio").innerText = data.biografia_artista;
    document.getElementById("current-precio-actual").innerText = Number(
      data.precio_actual,
    ).toFixed(2);

    document.getElementById("input-id-obra").value = data.id_obra;
    const bidInput = document.getElementById("input-monto");
    bidInput.min = Number(data.precio_actual) + 50;
    bidInput.value = Number(data.precio_actual) + 50;

    const timerDisplay = document.getElementById("detail-timer");
    const secondsLeft = calculateSecondsLeft(data.fecha_fin);

    if (secondsLeft > 0) {
      detailTimer = new Timer();
      detailTimer.start({
        countdown: true,
        startValues: { seconds: secondsLeft },
      });
      detailTimer.addEventListener("secondsUpdated", () => {
        let dias = detailTimer.getTimeValues().days;
          let horas = detailTimer.getTimeValues().hours;

          // Si falta más de 1 día, mostramos el texto fácil
          if (dias > 0) {
              timerDisplay.innerText = dias + " días, " + horas + " horas";
          } else {
              // Si falta menos de un día, mostramos el reloj normal
              timerDisplay.innerText = detailTimer.getTimeValues().toString();
          }
      });
    } else {
      timerDisplay.innerText = "FINALIZADA";
    }

    new Tabulator("#artwork-bids-table", {
      data: data.history || [],
      layout: "fitColumns",
      columns: [
        { title: "Mecenas", field: "nombre_usuario" },
        {
          title: "Monto",
          field: "monto",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
      ],
    });

    renderPriceChart(data.history || []);
  } catch (error) {
    console.error("Error en detalle:", error);
  }
}

function renderPriceChart(historyData) {
  const ctx = document.getElementById("price-chart").getContext("2d");
  if (priceChart) priceChart.destroy();

  const sortedHistory = [...historyData].reverse();
  const labels = sortedHistory.map((h) =>
    h.fecha ? h.fecha.split(" ")[1] : "",
  );
  const dataPoints = sortedHistory.map((h) => h.monto);

  priceChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Evolución €",
          data: dataPoints,
          borderColor: "#d4af37",
          fill: true,
          backgroundColor: "rgba(212, 175, 55, 0.1)",
        },
      ],
    },
    options: { responsive: true },
  });
}

// ============================================================================
// 4. TRANSACCIÓN DE PUJA
// ============================================================================
function setupBidForm() {
  const form = document.getElementById("bid-form");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = {
      id_obra: parseInt(document.getElementById("input-id-obra").value),
      monto: parseFloat(document.getElementById("input-monto").value),
    };

    try {
      const response = await fetch(RUTA_API + "sellar_transaccion", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();

      if (result.success) {
        document.getElementById("nav-saldo-disponible").innerText = Number(
          result.nuevo_saldo_disponible,
        ).toFixed(2);
        document.getElementById("nav-saldo-bloqueado").innerText = Number(
          result.nuevo_saldo_bloqueado,
        ).toFixed(2);
        openArtworkDetail(payload.id_obra);
      } else {
        alert("Error: " + result.message);
      }
    } catch (error) {
      console.error("Fallo en puja:", error);
    }
  });
}

// ============================================================================
// 5. TICKER GLOBAL
// ============================================================================
function initGlobalTicker() {
  globalTickerTable = new Tabulator("#global-bids-table", {
    data: [],
    layout: "fitColumns",
    height: "300px",
    columns: [
      { title: "Obra", field: "titulo_obra", widthGrow: 3 },
      { title: "Mecenas", field: "nombre_usuario", widthGrow: 2 },
      {
        title: "Monto",
        field: "monto",
        formatter: "money",
        formatterParams: { symbol: "€" },
      },
    ],
  });

  const fetchGlobalBids = async () => {
    try {
      const response = await fetch(RUTA_API + "obtener_ticker");
      const data = await response.json();
      globalTickerTable.setData(data || []);
    } catch (e) {
      console.warn("Ticker error");
    }
  };
  fetchGlobalBids();
  setInterval(fetchGlobalBids, 10000);
}

// ============================================================================
// 6. TALLER DEL ARTISTA (CARGA DE DATOS)
// ============================================================================
async function loadWorkshopData() {
  const container = document.getElementById("workshop-content");
  container.innerHTML = "<p class='text-gold'>Cargando vuestro taller...</p>";

  try {
    const response = await fetch(RUTA_API + "obtener_taller");
    const data = await response.json();

    container.innerHTML = `
      <div class="workshop-dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center;" class="mb-4">
            <h3 class="text-gold" style="margin:0;">Resumen de Actividad</h3>
            <button onclick="openNewArtworkModal()" class="btn-gold">+ Forjar Nueva Obra</button>
        </div>
        <div id="artist-inventory-table"></div>
      </div>
    `;

    new Tabulator("#artist-inventory-table", {
      data: data.artworks || [],
      layout: "fitColumns",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Precio Actual",
          field: "precio_actual",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        { title: "Estado", field: "estado", hozAlign: "center" },
      ],
    });
  } catch (error) {
    container.innerHTML =
      "<p class='text-danger'>Error al conectar con el Taller.</p>";
  }
}

// ============================================================================
// UTILIDADES
// ============================================================================
window.logout = async function () {
  await fetch(RUTA_API + "logout");
  window.location.href = "login.php";
};

function calculateSecondsLeft(dateStr) {
  if (!dateStr) return 0;
  const endDate = new Date(dateStr.replace(/-/g, "/"));
  const now = new Date();
  const diffMs = endDate - now;
  return diffMs > 0 ? Math.floor(diffMs / 1000) : 0;
}

window.openNewArtworkModal = function () {
  const container = document.getElementById("workshop-content");
  const template = document.getElementById("form-subir-obra-template");

  container.innerHTML = "";
  container.appendChild(template.content.cloneNode(true));

  const form = document.getElementById("form-nueva-obra");
  form.addEventListener("submit", handleUploadSubmit);
};

async function handleUploadSubmit(e) {
  e.preventDefault();
  const feedback = document.getElementById("upload-feedback");
  const formData = new FormData(e.target);

  feedback.innerText = "Sincronizando con la bóveda...";
  feedback.className = "mt-3 font-monospace small text-gold";

  try {
    const response = await fetch(RUTA_API + "subir_obra", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      feedback.innerText = "¡Obra forjada! Esperando validación del Senado.";
      feedback.style.color = "#4CAF50";
      setTimeout(loadWorkshopData, 2000);
    } else {
      feedback.innerText = "Error: " + result.message;
      feedback.style.color = "#dc3545";
    }
  } catch (error) {
    feedback.innerText = "Fallo crítico en la comunicación.";
  }
}

async function loadAdminData() {
  try {
    const response = await fetch(RUTA_API + "obtener_pendientes");
    const works = await response.json();

    new Tabulator("#admin-pending-table", {
      data: works || [],
      layout: "fitColumns",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        { title: "Artista", field: "nombre_artista", widthGrow: 2 },
        {
          title: "Precio Salida",
          field: "precio_inicial",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        {
          title: "Acción",
          formatter: (cell) => {
            const id = cell.getData().id_obra;
            return `<button onclick="approveWork(${id})" class="btn-gold" style="padding: 5px 10px; font-size: 0.8rem;">Validar</button>`;
          },
          hozAlign: "center",
          headerSort: false,
        },
      ],
      placeholder: "No hay obras pendientes de validación.",
    });
  } catch (error) {
    console.error("Error cargando obras pendientes:", error);
  }
}

window.approveWork = async function (id) {
  if (!confirm("¿Deseas validar esta obra para su publicación inmediata?"))
    return;

  try {
    const response = await fetch(RUTA_API + "aprobar_obra", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_obra: id }),
    });
    const result = await response.json();
    if (result.success) {
      alert("Lote validado con éxito.");
      loadAdminData();
    } else {
      alert("Error: " + result.message);
    }
  } catch (error) {
    console.error("Fallo al validar obra:", error);
  }
};

async function loadVaultData() {
  try {
    const userRes = await fetch(RUTA_API + "obtener_usuario");
    const user = await userRes.json();

    document.getElementById("vault-available").innerText =
      Number(user.saldo_disponible).toFixed(2) + " €";
    document.getElementById("vault-blocked").innerText =
      Number(user.saldo_bloqueado).toFixed(2) + " €";
    document.getElementById("vault-total").innerText =
      (Number(user.saldo_disponible) + Number(user.saldo_bloqueado)).toFixed(
        2,
      ) + " €";

    const bidsRes = await fetch(RUTA_API + "obtener_mis_pujas");
    const bids = await bidsRes.json();

    new Tabulator("#vault-bids-table", {
      data: bids || [],
      layout: "fitColumns",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Mi Puja",
          field: "mi_monto",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        {
          title: "Estado",
          field: "estado_puja",
        },
      ],
      placeholder: "Aún no has participado en ninguna subasta.",
    });
  } catch (error) {
    console.error("Error cargando la bóveda:", error);
  }
}

window.openDepositModal = function () {
  document.getElementById("modal-deposit").style.display = "flex";
};

window.closeDepositModal = function () {
  document.getElementById("modal-deposit").style.display = "none";
};

document
  .getElementById("form-deposit")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    const amount = document.getElementById("deposit-amount").value;

    try {
      const response = await fetch(RUTA_API + "ingresar_fondos", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ monto: amount }),
      });

      const result = await response.json();

      if (result.success) {
        alert("Transferencia completada. Sus fondos han sido actualizados.");
        closeDepositModal();
        loadUserData();
        if (document.getElementById("view-vault").style.display === "block")
          loadVaultData();
      } else {
        alert("Error: " + result.message);
      }
    } catch (error) {
      console.error("Fallo en la transacción:", error);
    }
  });
