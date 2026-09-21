/**
 * Frontend JavaScript - Sistem Pendaftaran Calon Pengurus Yayasan Mardiah
 * Menangani validasi form, AJAX Fetch, dan feedback interaktif.
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('registrationForm');
  const submitBtn = document.getElementById('submitBtn');
  const submitBtnText = document.getElementById('submitBtnText');
  const alertBox = document.getElementById('formAlertBox');
  const alertMessage = document.getElementById('formAlertMessage');

  // Modal elemen
  const successModal = document.getElementById('successModal');
  const modalName = document.getElementById('modalCandidateName');
  const modalPosition = document.getElementById('modalCandidatePosition');
  const modalRegId = document.getElementById('modalRegistrationId');
  const copyRegIdBtn = document.getElementById('copyRegIdBtn');
  const closeModalBtn = document.getElementById('closeModalBtn');

  // Input elements
  const nikInput = document.getElementById('nik');
  const phoneInput = document.getElementById('whatsapp');
  const motivasiInput = document.getElementById('motivasi');
  const motivasiCounter = document.getElementById('motivasiCounter');
  const genderContainer = document.getElementById('genderContainer');
  const genderRadios = form.querySelectorAll('input[name="jenis_kelamin"]');
  const ktpInput = document.getElementById('file_ktp');
  const ktpPreview = document.getElementById('ktpFilePreview');
  const ktpFileName = document.getElementById('ktpFileName');
  const ktpFileSize = document.getElementById('ktpFileSize');

  /**
   * Menentukan URL endpoint API yang fleksibel untuk lingkungan Vercel & XAMPP lokal
   */
  function resolveApiEndpoint() {
    const currentPath = window.location.pathname;

    // Jika dijalankan di bawah subdirektori XAMPP seperti /web_yys/public/ atau /web_yys/
    if (currentPath.includes('/web_yys/')) {
      const basePath = currentPath.substring(0, currentPath.indexOf('/web_yys/') + '/web_yys/'.length);
      return basePath + 'api/submit.php';
    }

    // Jika di Vercel atau root domain
    return '/api/submit.php';
  }

  const API_ENDPOINT = resolveApiEndpoint();

  // 1. Filter input hanya angka untuk NIK
  if (nikInput) {
    nikInput.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 16);
      clearFieldError(nikInput);
    });
  }

  // 2. Filter input untuk WhatsApp
  if (phoneInput) {
    phoneInput.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/[^0-9+]/g, '');
      clearFieldError(phoneInput);
    });
  }

  // 3. Karakter counter untuk textarea motivasi
  if (motivasiInput && motivasiCounter) {
    motivasiInput.addEventListener('input', () => {
      const len = motivasiInput.value.trim().length;
      motivasiCounter.textContent = `${len} / minimal 20 karakter`;
      if (len >= 20) {
        motivasiCounter.style.color = 'var(--primary)';
      } else {
        motivasiCounter.style.color = 'var(--text-light)';
      }
      clearFieldError(motivasiInput);
    });
  }

  // 4. File input preview untuk KTP
  if (ktpInput) {
    ktpInput.addEventListener('change', () => {
      clearFieldError(ktpInput);
      const file = ktpInput.files[0];
      if (file) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        if (ktpFileName) ktpFileName.textContent = file.name;
        if (ktpFileSize) ktpFileSize.textContent = `${sizeMb} MB`;
        if (ktpPreview) ktpPreview.style.display = 'block';

        if (file.size > 2 * 1024 * 1024) {
          setFieldError(ktpInput, 'Ukuran berkas KTP melebihi batas 2 MB.');
        }
      } else {
        if (ktpPreview) ktpPreview.style.display = 'none';
      }
    });
  }

  // 5. Reset error jenis kelamin saat radio dipilih
  genderRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (genderContainer) {
        genderContainer.classList.remove('is-invalid');
        const feedback = genderContainer.querySelector('.invalid-feedback');
        if (feedback) {
          feedback.textContent = '';
          feedback.style.display = 'none';
        }
      }
    });
  });

  // 5. Bersihkan status error saat field diubah
  const formInputs = form.querySelectorAll('input, select, textarea');
  formInputs.forEach((input) => {
    input.addEventListener('change', () => clearFieldError(input));
    input.addEventListener('focus', () => clearFieldError(input));
  });

  function clearFieldError(input) {
    if (!input) return;
    input.classList.remove('is-invalid');
    const formGroup = input.closest('.form-group');
    if (formGroup) {
      formGroup.classList.remove('is-invalid');
      const feedbackEl = formGroup.querySelector('.invalid-feedback');
      if (feedbackEl) {
        feedbackEl.textContent = '';
        feedbackEl.style.display = 'none';
      }
    }
  }

  function setFieldError(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    const formGroup = input.closest('.form-group');
    if (formGroup) {
      formGroup.classList.add('is-invalid');
      const feedbackEl = formGroup.querySelector('.invalid-feedback');
      if (feedbackEl) {
        feedbackEl.textContent = message;
        feedbackEl.style.display = 'block';
      }
    }
  }

  function showAlert(message, type = 'danger') {
    alertBox.className = `alert-box alert-${type}`;
    alertMessage.textContent = message;
    alertBox.style.display = 'flex';
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function hideAlert() {
    alertBox.style.display = 'none';
  }

  /**
   * Validasi Sisi Klien (Client-Side)
   */
  function validateClientSide() {
    let isValid = true;
    let firstInvalidField = null;

    // Reset semua error lama
    formInputs.forEach((input) => clearFieldError(input));
    if (genderContainer) {
      const genderFb = genderContainer.querySelector('.invalid-feedback');
      if (genderFb) {
        genderFb.textContent = '';
        genderFb.style.display = 'none';
      }
    }
    hideAlert();

    // Nama Lengkap
    const nama = form.nama_lengkap.value.trim();
    if (!nama) {
      setFieldError(form.nama_lengkap, 'Nama lengkap wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.nama_lengkap;
    } else if (nama.length < 3) {
      setFieldError(form.nama_lengkap, 'Nama lengkap minimal 3 karakter.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.nama_lengkap;
    }

    // NIK (16 digit)
    const nik = form.nik.value.trim();
    if (!nik) {
      setFieldError(form.nik, 'NIK wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.nik;
    } else if (nik.length !== 16 || !/^\d{16}$/.test(nik)) {
      setFieldError(form.nik, 'NIK harus terdiri dari tepat 16 digit angka KTP.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.nik;
    }

    // Jenis Kelamin
    const gender = form.querySelector('input[name="jenis_kelamin"]:checked');
    if (!gender) {
      if (genderContainer) {
        const feedback = genderContainer.querySelector('.invalid-feedback');
        if (feedback) {
          feedback.textContent = 'Silakan pilih jenis kelamin Anda.';
          feedback.style.display = 'block';
        }
      }
      isValid = false;
      if (!firstInvalidField) firstInvalidField = genderContainer;
    }

    // Tempat Lahir
    if (!form.tempat_lahir.value.trim()) {
      setFieldError(form.tempat_lahir, 'Tempat lahir wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.tempat_lahir;
    }

    // Tanggal Lahir
    const tglLahir = form.tanggal_lahir.value.trim();
    if (!tglLahir) {
      setFieldError(form.tanggal_lahir, 'Tanggal lahir wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.tanggal_lahir;
    } else {
      const birthDate = new Date(tglLahir);
      const today = new Date();
      const ageDiff = today.getFullYear() - birthDate.getFullYear();
      if (birthDate > today) {
        setFieldError(form.tanggal_lahir, 'Tanggal lahir tidak boleh di masa depan.');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = form.tanggal_lahir;
      } else if (ageDiff < 15) {
        setFieldError(form.tanggal_lahir, 'Usia minimal calon pengurus adalah 15 tahun.');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = form.tanggal_lahir;
      }
    }

    // Nomor WhatsApp
    const wa = form.whatsapp.value.trim();
    const cleanWa = wa.replace(/[^0-9+]/g, '');
    if (!wa) {
      setFieldError(form.whatsapp, 'Nomor WhatsApp wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.whatsapp;
    } else if (!/^(\+?62|0)8[0-9]{7,12}$/.test(cleanWa)) {
      setFieldError(form.whatsapp, 'Format nomor WhatsApp tidak valid (contoh: 081234567890).');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.whatsapp;
    }

    // Email
    const email = form.email.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email) {
      setFieldError(form.email, 'Alamat email wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.email;
    } else if (!emailRegex.test(email)) {
      setFieldError(form.email, 'Format alamat email tidak valid.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.email;
    }

    // Pendidikan Terakhir
    if (!form.pendidikan_terakhir.value) {
      setFieldError(form.pendidikan_terakhir, 'Silakan pilih jenjang pendidikan terakhir.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.pendidikan_terakhir;
    }

    // Posisi yang Diminati
    if (!form.posisi_diminati.value) {
      setFieldError(form.posisi_diminati, 'Silakan pilih divisi kepengurusan yang Anda minati.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.posisi_diminati;
    }

    // Motivasi
    const motivasi = form.motivasi.value.trim();
    if (!motivasi) {
      setFieldError(form.motivasi, 'Uraian motivasi wajib diisi.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.motivasi;
    } else if (motivasi.length < 20) {
      setFieldError(form.motivasi, 'Motivasi minimal berisi 20 karakter.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = form.motivasi;
    }

    // Upload File KTP
    const ktpFile = ktpInput && ktpInput.files ? ktpInput.files[0] : null;
    if (!ktpFile) {
      setFieldError(ktpInput, 'Foto / Scan KTP wajib diunggah.');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = ktpInput;
    } else {
      const allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
      const fileExt = ktpFile.name.split('.').pop().toLowerCase();
      if (!allowedExts.includes(fileExt)) {
        setFieldError(ktpInput, 'Format file harus berupa JPG, PNG, atau PDF.');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = ktpInput;
      } else if (ktpFile.size > 2 * 1024 * 1024) {
        setFieldError(ktpInput, 'Ukuran file KTP tidak boleh lebih dari 2 MB.');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = ktpInput;
      }
    }

    if (!isValid && firstInvalidField) {
      firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (typeof firstInvalidField.focus === 'function') {
        firstInvalidField.focus();
      }
    }

    return isValid;
  }

  /**
   * Submit Form Handler via AJAX Fetch (Multipart FormData)
   */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Validasi client-side terlebih dahulu
    if (!validateClientSide()) {
      showAlert('Mohon lengkapi atau perbaiki kolom formulir yang masih terdapat tanda peringatan.', 'danger');
      return;
    }

    // Siapkan payload data FormData untuk mendukung upload file
    const formData = new FormData(form);

    // Indikator Loading pada Tombol
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    submitBtnText.textContent = 'Mengunggah & Mengirimkan Berkas...';
    hideAlert();

    try {
      const response = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: {
          'Accept': 'application/json'
        },
        body: formData
      });

      let result;
      const rawText = await response.text();
      try {
        result = JSON.parse(rawText);
      } catch (jsonErr) {
        console.error('[Response parse error]:', rawText);
        showAlert('Terjadi kendala saat membaca respon dari server. Silakan coba kembali.', 'danger');
        return;
      }

      if (response.ok && result.status === 'success') {
        // Tampilkan Modal Sukses
        if (modalName) modalName.textContent = result.data?.nama_lengkap || form.nama_lengkap.value;
        if (modalPosition) modalPosition.textContent = result.data?.posisi_diminati || form.posisi_diminati.value;
        if (modalRegId) modalRegId.textContent = result.data?.registration_id || '-';

        successModal.style.display = 'flex';
        form.reset();
        if (ktpPreview) ktpPreview.style.display = 'none';
        if (motivasiCounter) motivasiCounter.textContent = '0 / minimal 20 karakter';
      } else {
        // Tangani pesan error dari server
        let errMsg = result.message || 'Terjadi kesalahan saat memproses pendaftaran.';
        if (result.detail) {
          errMsg += ` (${result.detail})`;
        }
        showAlert(errMsg, 'danger');

        // Jika ada spesifik field error
        if (result.errors && typeof result.errors === 'object') {
          for (const [fieldName, fieldMsg] of Object.entries(result.errors)) {
            const fieldEl = form.querySelector(`[name="${fieldName}"]`);
            if (fieldEl) {
              setFieldError(fieldEl, fieldMsg);
            }
          }
        }

        // Jika ada duplikasi NIK atau Email
        if (result.field) {
          const duplicateEl = form.querySelector(`[name="${result.field}"]`);
          if (duplicateEl) {
            setFieldError(duplicateEl, errMsg);
            duplicateEl.focus();
          }
        }
      }
    } catch (networkError) {
      console.error('[Fetch Error]:', networkError);
      showAlert(
        'Gagal terhubung ke server pendaftaran. Pastikan koneksi internet Anda stabil atau silakan coba beberapa saat lagi.',
        'danger'
      );
    } finally {
      // Kembalikan status tombol
      submitBtn.disabled = false;
      submitBtn.classList.remove('loading');
      submitBtnText.textContent = 'Kirim Berkas Pendaftaran';
    }
  });

  // Salin Registration ID ke Clipboard
  if (copyRegIdBtn && modalRegId) {
    copyRegIdBtn.addEventListener('click', async () => {
      const text = modalRegId.textContent.trim();
      try {
        await navigator.clipboard.writeText(text);
        const originalText = copyRegIdBtn.textContent;
        copyRegIdBtn.textContent = '✓ Tersalin!';
        copyRegIdBtn.style.backgroundColor = 'var(--primary)';
        copyRegIdBtn.style.color = '#fff';

        setTimeout(() => {
          copyRegIdBtn.textContent = originalText;
          copyRegIdBtn.style.backgroundColor = '';
          copyRegIdBtn.style.color = '';
        }, 2000);
      } catch (err) {
        console.warn('Gagal menyalin:', err);
      }
    });
  }

  // Tutup Modal Sukses
  if (closeModalBtn && successModal) {
    closeModalBtn.addEventListener('click', () => {
      successModal.style.display = 'none';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    successModal.addEventListener('click', (e) => {
      if (e.target === successModal) {
        successModal.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }
});
