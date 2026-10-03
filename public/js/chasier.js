const products = window.KASIR_PRODUCTS || [];

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/* ============================================
   BEEP (umpan balik scan: tinggi = berhasil, rendah = gagal)
============================================ */
let audioCtx = null;

function beep(success) {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.frequency.value = success ? 1200 : 300;
        gain.gain.value = 0.05;
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + (success ? 0.08 : 0.25));
    } catch (e) {
        // Browser tanpa WebAudio: abaikan saja.
    }
}

/* ============================================
   1. SEARCH PRODUCT
============================================ */
const searchInput = document.getElementById("searchProduct");
searchInput.addEventListener("input", searchProduct);

// Enter (juga dipakai scanner barcode, yang "mengetik" barcode lalu menekan Enter)
searchInput.addEventListener("keydown", function (event) {
    if (event.key !== "Enter") return;
    event.preventDefault();

    const keyword = searchInput.value.trim();
    if (!keyword) return;

    // 1) Scanner: barcode atau kode persis sama -> langsung masuk keranjang
    const exact = products.find(
        (p) => (p.barcode && p.barcode === keyword) || p.code.toLowerCase() === keyword.toLowerCase(),
    );
    if (exact) {
        addToCart(exact.id);
        beep(true);
        return;
    }

    // 2) Ketik manual: kalau hasil pencarian hanya satu, langsung tambahkan
    const results = findProducts(keyword);
    if (results.length === 1) {
        addToCart(results[0].id);
        beep(true);
        return;
    }

    // 3) Banyak hasil / tidak ditemukan: tampilkan daftar, kasir memilih sendiri
    searchProduct();
    if (results.length === 0) {
        beep(false);
        searchInput.select(); // scan berikutnya menimpa isi yang salah
    }
});

// Klik di luar kotak pencarian = tutup hasil
document.addEventListener("click", function (event) {
    if (!event.target.closest(".search-input")) {
        document.getElementById("productResults").style.display = "none";
    }
});

function findProducts(rawKeyword) {
    const keyword = rawKeyword.toLowerCase().trim();
    if (!keyword) return [];

    return products.filter(
        (product) =>
            product.name.toLowerCase().includes(keyword) ||
            product.code.toLowerCase().includes(keyword) ||
            (product.barcode || "").includes(keyword),
    );
}

function searchProduct() {
    const keyword = searchInput.value.trim();
    const resultBox = document.getElementById("productResults");

    if (!keyword) {
        resultBox.style.display = "none";
        return;
    }

    const results = findProducts(keyword);

    if (results.length === 0) {
        resultBox.innerHTML = `
            <div class="product-item">
                Barang tidak ditemukan
            </div>
        `;
    } else {
        resultBox.innerHTML = results
            .map(
                (product) => `
                <div class="product-item" onclick="addToCart(${product.id})">
                    <div>
                        <div class="product-name">${escapeHtml(product.name)}</div>
                        <div class="product-code">${escapeHtml(product.code)}${product.barcode ? " - " + escapeHtml(product.barcode) : ""}</div>
                    </div>
                    <div class="product-price">${formatRupiah(product.price)}</div>
                </div>
            `,
            )
            .join("");
    }

    resultBox.style.display = "block";
}

/* ============================================
   2. FORMAT RUPIAH
============================================ */
function formatRupiah(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);
}

/* ============================================
   CART
============================================ */
let cart = [];

/* ============================================
   ADD CART
============================================ */
function addToCart(productId) {
    const product = products.find((p) => p.id === productId);
    const existing = cart.find((item) => item.id === productId);

    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            ...product,
            qty: 1,
        });
    }

    searchInput.value = "";
    document.getElementById("productResults").style.display = "none";
    searchInput.focus();
    renderCart();
}

/* ============================================
   RENDER CART
============================================ */
function renderCart() {
    const body = document.getElementById("cartBody");

    if (cart.length === 0) {
        body.innerHTML = `
            <tr id="emptyRow">
                <td colspan="6" class="empty-cart">
                    Keranjang masih kosong.
                    <br>
                    Silakan cari atau scan barang.
                </td>
            </tr>
        `;
        calculateTotal();
        return;
    }

    body.innerHTML = cart
        .map(
            (item, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <strong>${escapeHtml(item.name)}</strong>
                    <div style="font-size:11px; color:#6c757d">
                        ${escapeHtml(item.code)}
                    </div>
                </td>
                <td>${formatRupiah(item.price)}</td>
                <td>
                    <div class="qty-control">
                        <button onclick="changeQty(${item.id}, -1)">−</button>
                        <input type="number" value="${item.qty}" min="1"
                               onchange="updateQty(${item.id}, this.value)">
                        <button onclick="changeQty(${item.id}, 1)">+</button>
                    </div>
                </td>
                <td class="text-right">
                    <strong>${formatRupiah(item.price * item.qty)}</strong>
                </td>
                <td class="text-center">
                    <button class="remove-btn" onclick="removeItem(${item.id})">×</button>
                </td>
            </tr>
        `,
        )
        .join("");

    calculateTotal();
}

/* ============================================
   CHANGE QTY
============================================ */
function changeQty(id, amount) {
    const item = cart.find((item) => item.id === id);
    if (!item) return;

    item.qty += amount;

    if (item.qty <= 0) {
        removeItem(id);
        return;
    }

    renderCart();
}

/* ============================================
   UPDATE QTY
============================================ */
function updateQty(id, qty) {
    const item = cart.find((item) => item.id === id);
    if (!item) return;

    item.qty = Math.max(1, parseInt(qty) || 1);
    renderCart();
}

/* ============================================
   REMOVE ITEM
============================================ */
function removeItem(id) {
    cart = cart.filter((item) => item.id !== id);
    renderCart();
}

/* ============================================
   CALCULATE TOTAL
============================================ */
function calculateTotal() {
    let totalQty = 0;
    let subtotal = 0;

    cart.forEach((item) => {
        totalQty += item.qty;
        subtotal += item.price * item.qty;
    });

    document.getElementById("totalQty").innerText = totalQty;
    document.getElementById("itemCount").innerText = totalQty + " Item";
    document.getElementById("subtotal").innerText = formatRupiah(subtotal);
    document.getElementById("grandTotal").innerText = formatRupiah(getGrandTotal());

    calculateChange();
}

/* ============================================
   CALCULATE CHANGE
============================================ */
function calculateChange() {
    const grandTotal = getGrandTotal();
    const payment = parseFloat(document.getElementById("payment").value) || 0;
    const change = payment - grandTotal;

    const changeBox = document.getElementById("changeBox");
    const changeElement = document.getElementById("change");

    if (change >= 0) {
        changeBox.classList.remove("short-payment");
        changeElement.innerText = formatRupiah(change);
        document.querySelector(".change-label").innerText = "KEMBALIAN";
    } else {
        changeBox.classList.add("short-payment");
        changeElement.innerText = formatRupiah(Math.abs(change));
        document.querySelector(".change-label").innerText = "UANG KURANG";
    }
}

/* ============================================
   GET GRAND TOTAL
============================================ */
function calcGrandTotal(subtotal, discountPercent, discountAmount, tax, otherFee) {
    const discount = Math.round((subtotal * discountPercent) / 100) + discountAmount;
    return Math.max(0, subtotal - discount + tax + otherFee);
}

function getGrandTotal() {
    let subtotal = 0;

    cart.forEach((item) => {
        subtotal += item.price * item.qty;
    });

    return calcGrandTotal(
        subtotal,
        numberValue("discountPercent"),
        numberValue("discountAmount"),
        numberValue("tax"),
        numberValue("otherFee"),
    );
}

/* ============================================
   PAYMENT METHOD
============================================ */
function selectPayment(button) {
    document.querySelectorAll(".payment-method button").forEach((btn) => {
        btn.classList.remove("active");
    });
    button.classList.add("active");
}

function getPaymentMethod() {
    const active = document.querySelector(".payment-method button.active");
    return active ? active.innerText : "Tunai";
}

/* ============================================
   HOLD TRANSACTION (Tahan)
============================================ */
const HELD_KEY = "kasir_held_transactions";

function loadHeld() {
    try {
        const list = JSON.parse(localStorage.getItem(HELD_KEY));
        return Array.isArray(list) ? list : [];
    } catch (error) {
        return [];
    }
}

function saveHeld(list) {
    try {
        localStorage.setItem(HELD_KEY, JSON.stringify(list));
        return true;
    } catch (error) {
        return false;
    }
}

function updateHeldCount() {
    const count = loadHeld().length;
    const button = document.getElementById("heldButton");
    button.innerText = "Ditahan (" + count + ")";
    button.classList.toggle("has-items", count > 0);
}

function holdTransaction() {
    if (cart.length === 0) {
        showToast("Tidak ada transaksi untuk ditahan.", "warning", 4000);
        return;
    }

    const list = loadHeld();

    list.push({
        id: Date.now(),
        heldAt: new Date().toISOString(),
        items: cart.map((item) => ({ id: item.id, qty: item.qty })),
        discountPercent: numberValue("discountPercent"),
        discountAmount: numberValue("discountAmount"),
        tax: numberValue("tax"),
        otherFee: numberValue("otherFee"),
        customerType: document.getElementById("customerType").value,
        customerPhone: document.getElementById("customerPhone").value,
        method: getPaymentMethod(),
    });

    if (!saveHeld(list)) {
        showToast("Transaksi gagal ditahan: penyimpanan browser tidak tersedia.", "error", 6000);
        return;
    }

    resetTransaction();
    updateHeldCount();
    showToast(
        "Transaksi ditahan. Lanjutkan lewat tombol <strong>Ditahan (" + list.length + ")</strong>.",
        "success",
        5000,
    );
}

function heldSummary(held) {
    let qty = 0;
    let subtotal = 0;

    held.items.forEach((row) => {
        const product = products.find((p) => p.id === row.id);
        if (!product) return;
        qty += row.qty;
        subtotal += product.price * row.qty;
    });

    return {
        qty,
        total: calcGrandTotal(subtotal, held.discountPercent, held.discountAmount, held.tax, held.otherFee),
    };
}

function renderHeldList() {
    const box = document.getElementById("heldList");
    const list = loadHeld();

    if (list.length === 0) {
        box.innerHTML = '<div class="held-empty">Tidak ada transaksi yang ditahan.</div>';
        return;
    }

    box.innerHTML = list
        .map((held) => {
            const info = heldSummary(held);
            const time = new Date(held.heldAt);
            const clock = pad(time.getHours()) + ":" + pad(time.getMinutes());

            return `
            <div class="held-row">
                <div class="held-info">
                    <div class="held-title">${info.qty} Item • ${formatRupiah(info.total)}</div>
                    <div class="held-sub">Ditahan ${clock} • ${escapeHtml(held.customerType)}</div>
                </div>
                <div class="held-actions">
                    <button type="button" class="btn btn-primary" data-resume="${held.id}">Lanjutkan</button>
                    <button type="button" class="btn btn-danger" data-delete="${held.id}">Hapus</button>
                </div>
            </div>`;
        })
        .join("");
}

function showHeldTransactions() {
    renderHeldList();
    document.getElementById("heldModal").hidden = false;
    const first = document.querySelector("#heldList [data-resume]") || document.getElementById("heldClose");
    first.focus();
}

function closeHeldTransactions() {
    document.getElementById("heldModal").hidden = true;
    searchInput.focus();
}

function resumeHeld(id) {
    const list = loadHeld();
    const held = list.find((h) => h.id === id);
    if (!held) return;

    closeHeldTransactions();

    if (cart.length > 0) {
        showToast("Selesaikan atau tahan dulu transaksi yang sedang berjalan.", "warning", 5000);
        return;
    }

    cart = [];
    let dropped = 0;

    held.items.forEach((row) => {
        const product = products.find((p) => p.id === row.id);
        if (product) {
            cart.push({ ...product, qty: row.qty });
        } else {
            dropped++; // barang sudah dihapus dari daftar produk
        }
    });

    document.getElementById("discountPercent").value = held.discountPercent;
    document.getElementById("discountAmount").value = held.discountAmount;
    document.getElementById("tax").value = held.tax;
    document.getElementById("otherFee").value = held.otherFee;
    document.getElementById("customerType").value = held.customerType;
    document.getElementById("customerPhone").value = held.customerPhone || "";

    const methodButton = Array.from(document.querySelectorAll(".payment-method button")).find(
        (btn) => btn.innerText === held.method,
    );
    if (methodButton) selectPayment(methodButton);

    saveHeld(list.filter((h) => h.id !== id));
    updateHeldCount();
    renderCart();

    if (dropped > 0) {
        showToast(dropped + " barang tidak ditemukan lagi dan dilewati.", "warning", 6000);
    }
}

async function deleteHeld(id) {
    const yes = await askConfirm({
        title: "Hapus transaksi ditahan?",
        text: "Transaksi ini akan dihapus permanen.",
        yesText: "Ya, hapus",
        noText: "Tidak",
    });

    // askConfirm mengembalikan fokus ke pencarian; daftar tetap terbuka.
    if (yes) {
        saveHeld(loadHeld().filter((h) => h.id !== id));
        updateHeldCount();
    }

    renderHeldList();
}

document.getElementById("heldList").addEventListener("click", function (event) {
    const resume = event.target.closest("[data-resume]");
    const remove = event.target.closest("[data-delete]");

    if (resume) resumeHeld(Number(resume.dataset.resume));
    if (remove) deleteHeld(Number(remove.dataset.delete));
});

document.getElementById("heldClose").addEventListener("click", closeHeldTransactions);

document.getElementById("heldModal").addEventListener("click", function (event) {
    if (event.target === this) closeHeldTransactions(); // klik area gelap = tutup
});

// Esc menutup daftar (kecuali dialog konfirmasi sedang terbuka di atasnya)
document.addEventListener(
    "keydown",
    function (event) {
        const heldOpen = !document.getElementById("heldModal").hidden;
        const confirmOpen = !document.getElementById("confirmModal").hidden;

        if (event.key === "Escape" && heldOpen && !confirmOpen) {
            event.preventDefault();
            event.stopPropagation();
            closeHeldTransactions();
        }
    },
    true,
);

/* ============================================
   TOAST
============================================ */
let toastTimer = null;

// type: "info" | "success" | "warning" | "error"
function showToast(html, type = "info", duration = 6000) {
    const toast = document.getElementById("toast");
    toast.innerHTML = html;
    toast.className = "toast show " + type;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("show"), duration);
}

function isModalOpen() {
    return !document.getElementById("confirmModal").hidden || !document.getElementById("heldModal").hidden;
}

function askConfirm({ title, text, yesText = "Ya", noText = "Tidak" }) {
    return new Promise((resolve) => {
        const modal = document.getElementById("confirmModal");
        const yes = document.getElementById("confirmYes");
        const no = document.getElementById("confirmNo");

        document.getElementById("confirmTitle").innerText = title;
        document.getElementById("confirmText").innerText = text;
        yes.innerText = yesText;
        no.innerText = noText;

        function close(result) {
            modal.hidden = true;
            yes.onclick = null;
            no.onclick = null;
            modal.onclick = null;
            document.removeEventListener("keydown", onKey, true);
            searchInput.focus();
            resolve(result);
        }

        function onKey(event) {
            if (event.key === "Escape") {
                event.preventDefault();
                event.stopPropagation();
                close(false);
            }
        }

        yes.onclick = () => close(true);
        no.onclick = () => close(false);
        modal.onclick = (event) => {
            if (event.target === modal) close(false); // klik area gelap = Tidak
        };
        document.addEventListener("keydown", onKey, true);

        modal.hidden = false;
        yes.focus();
    });
}

function printReceipt(url) {
    let frame = document.getElementById("receiptFrame");

    if (!frame) {
        frame = document.createElement("iframe");
        frame.id = "receiptFrame";
        frame.style.cssText = "position:fixed; right:0; bottom:0; width:0; height:0; border:0;";
        document.body.appendChild(frame);
    }

    frame.src = url + (url.includes("?") ? "&" : "?") + "print=1";
}

/* ============================================
   PAYMENT (simpan ke server, lalu cetak struk)
============================================ */
let isPaying = false;

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") : "";
}

function numberValue(id) {
    return parseFloat(document.getElementById(id).value) || 0;
}

async function processPayment() {
    if (isPaying) return;

    if (cart.length === 0) {
        showToast("Keranjang masih kosong.", "warning", 4000);
        searchInput.focus();
        return;
    }

    const total = getGrandTotal();
    const payment = numberValue("payment");

    if (payment < total) {
        showToast(
            "Uang pembayaran masih kurang <strong>" + formatRupiah(total - payment) + "</strong>.",
            "error",
            5000,
        );
        const paymentInput = document.getElementById("payment");
        paymentInput.focus();
        paymentInput.select();
        return;
    }

    const payButton = document.querySelector(".btn-pay");
    isPaying = true;
    payButton.disabled = true;

    try {
        const response = await fetch(window.KASIR_STORE_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken(),
            },
            body: JSON.stringify({
                items: cart.map((item) => ({ id: item.id, qty: item.qty })),
                discount_percent: numberValue("discountPercent"),
                discount_amount: Math.round(numberValue("discountAmount")),
                tax: Math.round(numberValue("tax")),
                other_fee: Math.round(numberValue("otherFee")),
                paid_amount: Math.round(payment),
                payment_method: getPaymentMethod(),
                customer_type: document.getElementById("customerType").value,
                customer_phone: document.getElementById("customerPhone").value.trim() || null,
            }),
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = result.errors
                ? Object.values(result.errors)[0][0]
                : result.message || "Gagal menyimpan transaksi (kode " + response.status + "). Muat ulang halaman lalu coba lagi.";
            showToast(escapeHtml(firstError), "error", 7000);
            return;
        }

        const trx = result.transaction;

        showToast(
            "<strong>" + escapeHtml(trx.number) + "</strong> tersimpan.<br>" +
            "Total " + formatRupiah(trx.grand_total) + " • Kembali " + formatRupiah(trx.change_amount) + "<br>" +
            '<a href="' + escapeHtml(result.receipt_url) + '" target="_blank" rel="noopener">Cetak ulang struk</a>',
            "success",
            12000,
        );

        printReceipt(result.receipt_url);

        document.getElementById("transactionNumber").innerText = result.next_number;
        resetTransaction();
    } catch (error) {
        showToast("Tidak dapat terhubung ke server. Transaksi belum tersimpan.", "error", 7000);
    } finally {
        isPaying = false;
        payButton.disabled = false;
    }
}

/* ============================================
   CANCEL TRANSACTION
============================================ */
async function cancelTransaction() {
    // Keranjang kosong: tidak ada yang hilang, cukup bersihkan isian tanpa bertanya.
    if (cart.length === 0) {
        resetTransaction();
        showToast("Isian dibersihkan.", "info", 3000);
        return;
    }

    const yes = await askConfirm({
        title: "Batalkan transaksi?",
        text: "Semua barang di keranjang akan dihapus.",
        yesText: "Ya, batalkan",
        noText: "Tidak",
    });

    if (yes) {
        resetTransaction();
        showToast("Transaksi dibatalkan.", "info", 3000);
    }
}

function resetTransaction() {
    cart = [];

    document.getElementById("payment").value = "";
    document.getElementById("discountPercent").value = 0;
    document.getElementById("discountAmount").value = 0;
    document.getElementById("tax").value = 0;
    document.getElementById("otherFee").value = 0;
    document.getElementById("customerType").selectedIndex = 0;
    document.getElementById("customerPhone").value = "";
    selectPayment(document.querySelector(".payment-method button"));

    renderCart();
    searchInput.focus();
}

/* ============================================
   TRANSACTION NUMBER & DATE
============================================ */
function pad(n) {
    return String(n).padStart(2, "0");
}

(function setTransactionInfo() {
    const now = new Date();

    if (window.KASIR_NEXT_NUMBER) {
        // Nomor berikutnya dihitung oleh server (KasirController)
        document.getElementById("transactionNumber").innerText = window.KASIR_NEXT_NUMBER;
    } else {
        const code = now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate());
        document.getElementById("transactionNumber").innerText = "TRX-" + code + "-001";
    }

    document.getElementById("currentDate").innerText = now.toLocaleString("id-ID");
})();

/* ============================================
   KEYBOARD SHORTCUT
============================================ */
document.addEventListener("keydown", function (event) {
    // Saat dialog konfirmasi terbuka, tombol pintas dimatikan
    if (isModalOpen()) return;

    // F2 = fokus pencarian
    if (event.key === "F2") {
        event.preventDefault();
        searchInput.focus();
    }

    // F4 = fokus pembayaran
    if (event.key === "F4") {
        event.preventDefault();
        document.getElementById("payment").focus();
    }

    // Scanner: jika tidak sedang mengetik di kolom isian, arahkan ketikan ke kolom pencarian
    const tag = document.activeElement ? document.activeElement.tagName : "";
    const isTyping = ["INPUT", "TEXTAREA", "SELECT"].includes(tag);
    if (!isTyping && !event.ctrlKey && !event.altKey && !event.metaKey && event.key.length === 1) {
        searchInput.focus();
    }

    // ESC = batal
    if (event.key === "Escape" && cart.length > 0) {
        cancelTransaction();
    }
});

/* ============================================
   INITIAL
============================================ */
renderCart();
updateHeldCount();
searchInput.focus();
