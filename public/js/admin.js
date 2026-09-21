/**
 * Admin Panel JavaScript - Yayasan Mardiah
 * Menangani autentikasi, status sesi, dan aksi CRUD komplit untuk pendaftar.
 */

document.addEventListener('DOMContentLoaded', () => {
  // Base API Resolvers
  function resolveApiBase() {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/web_yys/')) {
      const base = currentPath.substring(0, currentPath.indexOf('/web_yys/') + '/web_yys/'.length);
      return {
        auth: base + 'api/admin-auth.php',
        crud: base + 'api/admin-crud.php'
      };
    }
    return {
      auth: '/api/admin-auth.php',
      crud: '/api/admin-crud.php'
    };
  }

  const API = resolveApiBase();

  // DOM Elements - Views
  const loginView = document.getElementById('adminLoginView');
  const dashboardView = document.getElementById('adminDashboardView');
  const loginForm = document.getElementById('adminLoginForm');
  const loginAlert = document.getElementById('loginAlert');
  const loginBtn = document.getElementById('loginBtn');
  const logoutBtn = document.getElementById('logoutBtn');
  const adminNameEl = document.getElementById('adminProfileName');

  // DOM Elements - Metrics
  const statTotal = document.getElementById('statTotal');
  const statReview = document.getElementById('statReview');
  const statLolos = document.getElementById('statLolos');
  const statWawancara = document.getElementById('statWawancara');
  const statDiterima = document.getElementById('statDiterima');

  // DOM Elements - Controls & Table
  const searchInput = document.getElementById('tableSearchInput');
  const filterDivisi = document.getElementById('filterDivisi');
  const filterStatus = document.getElementById('filterStatus');
  const resetFilterBtn = document.getElementById('resetFilterBtn');
  const tableBody = document.getElementById('candidatesTableBody');
  const paginationInfo = document.getElementById('paginationInfo');
  const paginationPrev = document.getElementById('paginationPrev');
  const paginationNext = document.getElementById('paginationNext');
  const btnExportCsv = document.getElementById('btnExportCsv');

  // Modals
  const detailModal = document.getElementById('detailModal');
  const editModal = document.getElementById('editModal');
  const addModal = document.getElementById('addModal');
  const deleteModal = document.getElementById('deleteModal');

  let currentPage = 1;
  let totalPages = 1;
  let searchTimeout = null;
  let candidateToDeleteId = null;

  // Helper: Get Stored Token
  function getToken() {
    return localStorage.getItem('ym_admin_token') || '';
  }

  function setToken(token) {
    localStorage.setItem('ym_admin_token', token);
  }

  function clearToken() {
    localStorage.removeItem('ym_admin_token');
    localStorage.removeItem('ym_admin_profile');
  }

  // Helper: Authenticated Fetch Wrapper
  async function authFetch(url, options = {}) {
    const token = getToken();
    const headers = {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Auth-Token': token,
      ...(options.headers || {})
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    const res = await fetch(url, { ...options, headers });
    if (res.status === 401) {
      clearToken();
      showLoginView('Sesi Anda telah berakhir. Silakan login kembali.');
      throw new Error('Unauthorized');
    }
    return res;
  }

  // 1. Inisialisasi: Cek Status Sesi
  async function initSession() {
    const token = getToken();
    if (!token) {
      showLoginView();
      return;
    }

    try {
      const res = await authFetch(`${API.auth}?action=me`);
      const data = await res.json();
      if (data.status === 'success') {
        const admin = data.data.admin;
        if (adminNameEl) adminNameEl.textContent = admin.nama || admin.username;
        showDashboardView();
        loadDashboard();
      } else {
        showLoginView();
      }
    } catch (e) {
      showLoginView();
    }
  }

  function showLoginView(alertMsg = '') {
    if (loginView) loginView.style.display = 'flex';
    if (dashboardView) dashboardView.style.display = 'none';
    if (alertMsg && loginAlert) {
      loginAlert.textContent = alertMsg;
      loginAlert.style.display = 'block';
    }
  }

  function showDashboardView() {
    if (loginView) loginView.style.display = 'none';
    if (dashboardView) dashboardView.style.display = 'block';
  }

  // 2. Handler Login Form
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      loginAlert.style.display = 'none';
      loginBtn.disabled = true;
      loginBtn.textContent = 'Memverifikasi...';

      const username = loginForm.username.value.trim();
      const password = loginForm.password.value;

      try {
        const res = await fetch(`${API.auth}?action=login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });
        const result = await res.json();

        if (res.ok && result.status === 'success') {
          setToken(result.data.token);
          if (adminNameEl) adminNameEl.textContent = result.data.admin.nama;
          showDashboardView();
          loadDashboard();
        } else {
          loginAlert.textContent = result.message || 'Username atau password salah.';
          loginAlert.style.display = 'block';
        }
      } catch (err) {
        loginAlert.textContent = 'Gagal menghubungi server autentikasi.';
        loginAlert.style.display = 'block';
      } finally {
        loginBtn.disabled = false;
        loginBtn.textContent = 'Masuk ke Dashboard';
      }
    });
  }

  // 3. Handler Logout
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      if (confirm('Apakah Anda yakin ingin keluar dari panel admin?')) {
        try {
          await authFetch(`${API.auth}?action=logout`, { method: 'POST' });
        } catch (e) { }
        clearToken();
        showLoginView('Anda telah berhasil keluar.');
      }
    });
  }

  // 4. Load Dashboard (Metrics & Table)
  function loadDashboard() {
    loadStats();
    loadCandidates(1);
  }

  // Load Metrics
  async function loadStats() {
    try {
      const res = await authFetch(`${API.crud}?action=stats`);
      const result = await res.json();
      if (result.status === 'success') {
        const d = result.data;
        if (statTotal) statTotal.textContent = d.total_pendaftar || 0;
        if (statReview) statReview.textContent = d.status_counts['Menunggu Review'] || 0;
        if (statLolos) statLolos.textContent = d.status_counts['Lolos Berkas'] || 0;
        if (statWawancara) statWawancara.textContent = d.status_counts['Wawancara'] || 0;
        if (statDiterima) statDiterima.textContent = d.status_counts['Diterima'] || 0;
      }
    } catch (e) { }
  }

  // Helper: Status Pill HTML
  function getStatusBadge(status) {
    const map = {
      'Menunggu Review': 'status-review',
      'Lolos Berkas': 'status-lolos',
      'Wawancara': 'status-wawancara',
      'Diterima': 'status-diterima',
      'Ditolak': 'status-ditolak'
    };
    const cls = map[status] || 'status-review';
    return `<span class="status-pill ${cls}">${status}</span>`;
  }

  // Load Table Candidates
  async function loadCandidates(page = 1) {
    currentPage = page;
    const q = searchInput ? searchInput.value.trim() : '';
    const divisi = filterDivisi ? filterDivisi.value : '';
    const status = filterStatus ? filterStatus.value : '';

    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 2rem; color: var(--text-muted);">Memuat data pendaftar...</td></tr>`;

    try {
      const url = `${API.crud}?action=list&page=${page}&limit=10&q=${encodeURIComponent(q)}&divisi=${encodeURIComponent(divisi)}&status=${encodeURIComponent(status)}`;
      const res = await authFetch(url);
      const result = await res.json();

      if (result.status === 'success') {
        const { items, total_items, current_page, total_pages } = result.data;
        totalPages = total_pages;

        if (items.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="8" style="text-align:center; padding: 3rem; color: var(--text-light);">
                Tidak ada data calon pengurus yang cocok dengan kriteria pencarian/filter.
              </td>
            </tr>
          `;
        } else {
          tableBody.innerHTML = items.map((item, index) => {
            const no = (current_page - 1) * 10 + (index + 1);
            const dateStr = item.created_at ? new Date(item.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';
            return `
              <tr>
                <td><strong>${no}</strong></td>
                <td>
                  <div style="font-weight:700; color:var(--text-main);">${escapeHtml(item.nama_lengkap)}</div>
                  <small style="color:var(--text-light); font-family:monospace;">${escapeHtml(item.nik)}</small>
                </td>
                <td>
                  <span style="display:inline-block; font-weight:600; color:var(--primary); font-size:0.85rem;">
                    ${escapeHtml(item.posisi_diminati)}
                  </span>
                </td>
                <td>
                  <div>📱 <a href="https://wa.me/${formatWaLink(item.whatsapp)}" target="_blank" style="color:var(--primary); text-decoration:none; font-weight:600;">${escapeHtml(item.whatsapp)}</a></div>
                  <small style="color:var(--text-muted);">✉️ ${escapeHtml(item.email)}</small>
                </td>
                <td><span style="font-size:0.85rem; color:var(--text-muted);">${escapeHtml(item.pendidikan_terakhir)}</span></td>
                <td><span style="font-size:0.825rem; color:var(--text-muted);">${dateStr}</span></td>
                <td>${getStatusBadge(item.status_seleksi)}</td>
                <td>
                  <div class="action-btn-group">
                    <button type="button" class="btn-table-action btn-action-view" onclick="window.viewCandidateDetail(${item.id})">
                      👁️ Detail
                    </button>
                    <button type="button" class="btn-table-action btn-action-edit" onclick="window.openEditCandidateModal(${item.id})">
                      ✏️ Edit
                    </button>
                    <button type="button" class="btn-table-action btn-action-delete" onclick="window.openDeleteCandidateModal(${item.id}, '${escapeJs(item.nama_lengkap)}')">
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            `;
          }).join('');
        }

        // Update Pagination Controls
        if (paginationInfo) {
          paginationInfo.textContent = `Menampilkan halaman ${current_page} dari ${total_pages} (Total: ${total_items} pendaftar)`;
        }
        if (paginationPrev) paginationPrev.disabled = current_page <= 1;
        if (paginationNext) paginationNext.disabled = current_page >= total_pages;
      }
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 2rem; color:var(--danger);">Gagal memuat data pendaftar.</td></tr>`;
    }
  }

  // Formatting WhatsApp Link
  function formatWaLink(phone) {
    let clean = (phone || '').replace(/[^0-9]/g, '');
    if (clean.startsWith('08')) {
      clean = '628' + clean.substring(2);
    }
    return clean;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
  }

  // 5. Filter & Search Listeners
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadCandidates(1), 350);
    });
  }

  if (filterDivisi) filterDivisi.addEventListener('change', () => loadCandidates(1));
  if (filterStatus) filterStatus.addEventListener('change', () => loadCandidates(1));

  if (resetFilterBtn) {
    resetFilterBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      if (filterDivisi) filterDivisi.value = '';
      if (filterStatus) filterStatus.value = '';
      loadCandidates(1);
    });
  }

  if (paginationPrev) {
    paginationPrev.addEventListener('click', () => {
      if (currentPage > 1) loadCandidates(currentPage - 1);
    });
  }

  if (paginationNext) {
    paginationNext.addEventListener('click', () => {
      if (currentPage < totalPages) loadCandidates(currentPage + 1);
    });
  }

  // 6. Export CSV Handler
  if (btnExportCsv) {
    btnExportCsv.addEventListener('click', () => {
      const token = getToken();
      window.location.href = `${API.crud}?action=export&token=${encodeURIComponent(token)}`;
    });
  }

  // 7. Modal: View Candidate Detail
  window.viewCandidateDetail = async function (id) {
    try {
      const res = await authFetch(`${API.crud}?action=get&id=${id}`);
      const result = await res.json();
      if (result.status === 'success') {
        const d = result.data;
        document.getElementById('detailNama').textContent = d.nama_lengkap;
        document.getElementById('detailNik').textContent = d.nik;
        document.getElementById('detailGender').textContent = d.jenis_kelamin;
        document.getElementById('detailTtl').textContent = `${d.tempat_lahir}, ${d.tanggal_lahir}`;
        document.getElementById('detailWa').textContent = d.whatsapp;
        document.getElementById('detailEmail').textContent = d.email;
        document.getElementById('detailAlamat').textContent = d.alamat || '-';
        document.getElementById('detailPendidikan').textContent = d.pendidikan_terakhir;
        document.getElementById('detailPosisi').textContent = d.posisi_diminati;
        document.getElementById('detailMotivasi').textContent = d.motivasi;
        document.getElementById('detailOrganisasi').textContent = d.riwayat_organisasi || '-';
        document.getElementById('detailCreated').textContent = d.created_at;
        document.getElementById('detailRegId').textContent = d.uuid;

        // Render Berkas KTP
        const ktpValEl = document.getElementById('detailKtpVal');
        if (ktpValEl) {
          if (d.file_ktp) {
            const isPdf = d.file_ktp.toLowerCase().endsWith('.pdf');
            if (isPdf) {
              ktpValEl.innerHTML = `
                <a href="${escapeHtml(d.file_ktp)}" target="_blank" class="btn-table-action btn-action-view" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                  📄 Buka / Unduh Dokumen KTP (PDF)
                </a>
              `;
            } else {
              ktpValEl.innerHTML = `
                <div style="display:flex; align-items:flex-start; gap:1rem; flex-wrap:wrap;">
                  <a href="${escapeHtml(d.file_ktp)}" target="_blank">
                    <img src="${escapeHtml(d.file_ktp)}" alt="Foto KTP" style="max-width: 260px; max-height: 160px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); cursor: pointer;" title="Klik untuk membuka ukuran penuh">
                  </a>
                  <div>
                    <a href="${escapeHtml(d.file_ktp)}" target="_blank" class="btn-table-action btn-action-view" style="font-size: 0.85rem; padding: 0.4rem 0.85rem; display:inline-block; margin-bottom: 0.4rem;">
                      🔍 Buka Foto Asli
                    </a>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Klik gambar atau tombol untuk memeriksa keaslian KTP.</div>
                  </div>
                </div>
              `;
            }
          } else {
            ktpValEl.innerHTML = `<span style="color: var(--text-light); font-style: italic;">Tidak ada berkas KTP terlampir.</span>`;
          }
        }

        // Quick status selector
        const statusSelect = document.getElementById('detailStatusSelect');
        if (statusSelect) {
          statusSelect.value = d.status_seleksi;
          statusSelect.onchange = async () => {
            const newStatus = statusSelect.value;
            await authFetch(`${API.crud}?action=update-status&id=${id}`, {
              method: 'POST',
              body: { status_seleksi: newStatus }
            });
            loadStats();
            loadCandidates(currentPage);
          };
        }

        detailModal.style.display = 'flex';
      }
    } catch (e) {
      alert('Gagal memuat detail pendaftar.');
    }
  };

  // 8. Modal: Edit Candidate
  const editForm = document.getElementById('editCandidateForm');
  window.openEditCandidateModal = async function (id) {
    try {
      const res = await authFetch(`${API.crud}?action=get&id=${id}`);
      const result = await res.json();
      if (result.status === 'success') {
        const d = result.data;
        editForm.edit_id.value = d.id;
        editForm.nama_lengkap.value = d.nama_lengkap;
        editForm.nik.value = d.nik;
        editForm.jenis_kelamin.value = d.jenis_kelamin;
        editForm.tempat_lahir.value = d.tempat_lahir;
        editForm.tanggal_lahir.value = d.tanggal_lahir;
        editForm.whatsapp.value = d.whatsapp;
        editForm.email.value = d.email;
        editForm.alamat.value = d.alamat || '';
        editForm.pendidikan_terakhir.value = d.pendidikan_terakhir;
        editForm.posisi_diminati.value = d.posisi_diminati;
        editForm.status_seleksi.value = d.status_seleksi;
        editForm.motivasi.value = d.motivasi;
        editForm.riwayat_organisasi.value = d.riwayat_organisasi || '';

        editModal.style.display = 'flex';
      }
    } catch (e) {
      alert('Gagal membuka form edit.');
    }
  };

  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = editForm.edit_id.value;
      const formData = new FormData(editForm);
      const payload = {};
      formData.forEach((val, key) => payload[key] = val);

      try {
        const res = await authFetch(`${API.crud}?action=update&id=${id}`, {
          method: 'POST',
          body: payload
        });
        const result = await res.json();
        if (res.ok && result.status === 'success') {
          editModal.style.display = 'none';
          loadStats();
          loadCandidates(currentPage);
        } else {
          alert(result.message || 'Gagal menyimpan perubahan.');
        }
      } catch (err) {
        alert('Terjadi kesalahan saat memperbarui data.');
      }
    });
  }

  // 9. Modal: Add Candidate Manually
  const addBtn = document.getElementById('btnAddCandidate');
  const addForm = document.getElementById('addCandidateForm');

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      if (addForm) addForm.reset();
      addModal.style.display = 'flex';
    });
  }

  if (addForm) {
    addForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(addForm);
      const payload = {};
      formData.forEach((val, key) => payload[key] = val);

      try {
        const res = await authFetch(`${API.crud}?action=create`, {
          method: 'POST',
          body: payload
        });
        const result = await res.json();
        if (res.ok && result.status === 'success') {
          addModal.style.display = 'none';
          loadStats();
          loadCandidates(1);
        } else {
          alert(result.message || 'Gagal menambahkan pendaftar.');
        }
      } catch (err) {
        alert('Terjadi kesalahan saat menambahkan data.');
      }
    });
  }

  // 10. Modal: Delete Confirmation
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  window.openDeleteCandidateModal = function (id, name) {
    candidateToDeleteId = id;
    document.getElementById('deleteCandidateName').textContent = name;
    deleteModal.style.display = 'flex';
  };

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', async () => {
      if (!candidateToDeleteId) return;
      try {
        const res = await authFetch(`${API.crud}?action=delete&id=${candidateToDeleteId}`, {
          method: 'POST'
        });
        const result = await res.json();
        if (res.ok && result.status === 'success') {
          deleteModal.style.display = 'none';
          candidateToDeleteId = null;
          loadStats();
          loadCandidates(currentPage);
        } else {
          alert(result.message || 'Gagal menghapus data.');
        }
      } catch (e) {
        alert('Gagal menghapus pendaftar.');
      }
    });
  }

  // 11. Modal: Ganti Password Admin
  const btnChangePassword = document.getElementById('btnChangePassword');
  const passwordModal = document.getElementById('passwordModal');
  const changePasswordForm = document.getElementById('changePasswordForm');
  const passwordAlert = document.getElementById('passwordAlert');
  const savePasswordBtn = document.getElementById('savePasswordBtn');

  if (btnChangePassword && passwordModal) {
    btnChangePassword.addEventListener('click', () => {
      if (changePasswordForm) changePasswordForm.reset();
      if (passwordAlert) {
        passwordAlert.style.display = 'none';
        passwordAlert.textContent = '';
      }
      passwordModal.style.display = 'flex';
    });
  }

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const oldPassword = changePasswordForm.old_password.value;
      const newPassword = changePasswordForm.new_password.value;
      const confirmPassword = changePasswordForm.confirm_password.value;

      passwordAlert.style.display = 'none';

      if (newPassword.length < 6) {
        passwordAlert.className = 'alert-box alert-danger';
        passwordAlert.textContent = 'Password baru minimal harus 6 karakter.';
        passwordAlert.style.display = 'block';
        return;
      }

      if (newPassword !== confirmPassword) {
        passwordAlert.className = 'alert-box alert-danger';
        passwordAlert.textContent = 'Konfirmasi password baru tidak cocok dengan password baru.';
        passwordAlert.style.display = 'block';
        return;
      }

      savePasswordBtn.disabled = true;
      savePasswordBtn.textContent = 'Menyimpan Password...';

      try {
        const res = await authFetch(`${API.auth}?action=change-password`, {
          method: 'POST',
          body: {
            old_password: oldPassword,
            new_password: newPassword
          }
        });
        const result = await res.json();

        if (res.ok && result.status === 'success') {
          passwordAlert.className = 'alert-box alert-success';
          passwordAlert.textContent = '✓ Password berhasil diperbarui! Gunakan password baru untuk login berikutnya.';
          passwordAlert.style.display = 'block';
          changePasswordForm.reset();

          setTimeout(() => {
            if (passwordModal) passwordModal.style.display = 'none';
          }, 1800);
        } else {
          passwordAlert.className = 'alert-box alert-danger';
          passwordAlert.textContent = result.message || 'Gagal mengganti password.';
          passwordAlert.style.display = 'block';
        }
      } catch (err) {
        passwordAlert.className = 'alert-box alert-danger';
        passwordAlert.textContent = 'Terjadi kesalahan koneksi saat mengganti password.';
        passwordAlert.style.display = 'block';
      } finally {
        savePasswordBtn.disabled = false;
        savePasswordBtn.textContent = 'Simpan Password Baru';
      }
    });
  }

  // Global Modal Close Listeners
  document.querySelectorAll('.btn-close-modal, .btn-close-x, .btn-cancel-modal').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (detailModal) detailModal.style.display = 'none';
      if (editModal) editModal.style.display = 'none';
      if (addModal) addModal.style.display = 'none';
      if (deleteModal) deleteModal.style.display = 'none';
      if (passwordModal) passwordModal.style.display = 'none';
    });
  });

  window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
      e.target.style.display = 'none';
    }
  });

  // Jalankan inisialisasi sesi saat halaman dimuat
  initSession();
});
