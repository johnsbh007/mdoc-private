<?php
// D:\xampp\htdocs\account_web\components\layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_username = htmlspecialchars($_SESSION['full_name'] ?? 'ຜູ້ໃຊ້ງານ');
$_current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="lo">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eDoc</title>
  
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
  
  <!-- APIMS CSS & Custom Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
      body {
          font-family: 'Noto Sans Lao', sans-serif;
      }
  </style>
    <?php if (!empty($_SESSION['is_view_only'])): ?>
  <style>
      /* View-Only Mode Restriction */
      .btn-success, .btn-primary, .btn-warning, .btn-danger, .btn-edit, .btn-delete {
          display: none !important;
      }
      input[type="submit"], button[type="submit"] {
          display: none !important;
      }
  </style>
  <?php endif; ?>
  <link rel="stylesheet" href="/css/apims.css?v=<?= time() ?>">
  
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
  
  <style>
        /* APIMS Hidden Modal Design */
        .apims-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
            display: none; /* hidden by default */
        }

        .apims-modal {
            background: #fff;
            width: 100%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(0);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }

        .apims-modal .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .apims-modal .modal-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

    /* Global Form Label Standard per User Request */
    .form-label, .form-label-lao, .form-label-eng {
        font-size: 16px !important;
        font-weight: 500 !important;
        color: #666 !important;
    }
    .form-label::after, .form-label-lao::after, .form-label-eng::after {
        content: " :";
    }
    
    body {
        background-color: var(--bg-light);
    }
        .apims-modal .modal-close {
            background: transparent;
            border: none;
            font-size: 1.2rem;
            color: #888;
            cursor: pointer;
            transition: 0.2s;
        }
        .apims-modal .modal-close:hover {
            color: #dc3545;
            transform: scale(1.1);
        }

        .apims-modal .modal-body {
            padding: 1.5rem;
        }

        .apims-modal .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #f0f0f0;
            background: #fdfdfd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body>

<div class="apims-wrapper" id="apimsWrapper">

  <!-- ===============================
       SIDEBAR
       =============================== -->
  <aside class="apims-sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
      <img src="/images/Logo.jpg" alt="Logo" class="sidebar-logo">
      <div class="sidebar-brand-text">
         <div class="sidebar-brand-name">eDoc</div>
        <div class="sidebar-brand-sub">ລະບົບຄຸ້ມຄອງເອກະສານ</div>
      </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">

      <!-- Dashboard -->
      <a class="sidebar-link <?= $_current === 'index.php' ? 'active-page' : '' ?>" href="index.php">
        <i class="fas fa-th-large"></i>
        <span>ໜ້າຫຼັກ</span>
      </a>

      <!-- 2. ຂໍ້ມູນກ່ຽວຂ້ອງ (Master Data) -->
      <a class="sidebar-link has-children <?= in_array($_current, ['list_donors.php', 'donors.php', 'list_provinces.php', 'list_asset_categories.php', 'list_assets.php', 'list_categories.php', 'list_group_categories.php', 'list_outputs.php', 'list_activities.php', 'list_account_groups.php', 'list_chart_of_accounts.php', 'list_banks.php', 'list_books.php', 'list_budgets.php', 'list_budget_outputs.php', 'list_budget_summary.php', 'list_budget_activities.php', 'list_contracts.php', 'list_was.php']) ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#nav-master" role="button">
        <i class="fas fa-database sidebar-icon"></i>
        <span>ຂໍ້ມູນພື້ນຖານ</span>
        <i class="fas fa-chevron-right sidebar-arrow"></i>
      </a>
      <div class="collapse <?= in_array($_current, ['list_donors.php', 'donors.php', 'list_provinces.php', 'list_asset_categories.php', 'list_assets.php', 'list_categories.php', 'list_group_categories.php', 'list_outputs.php', 'list_activities.php', 'list_account_groups.php', 'list_chart_of_accounts.php', 'list_banks.php', 'list_books.php', 'list_budgets.php', 'list_budget_outputs.php', 'list_budget_summary.php', 'list_budget_activities.php', 'list_contracts.php', 'list_was.php']) ? 'show' : '' ?>" id="nav-master">
        <div class="sidebar-sub">

          <!-- ຂໍ້ມູນພື້ນຖານ (eDoc) - ລຳດັບຕາມທີ່ກຳນົດ -->
          <a href="../pages/list_edoc_province.php"    class="<?= in_array($_current, ['list_edoc_province.php']) ? 'active' : '' ?>">ລາຍຊື່ແຂວງ</a>
          <a href="../pages/list_edoc_district.php"    class="<?= in_array($_current, ['list_edoc_district.php']) ? 'active' : '' ?>">ລາຍຊື່ເມືອງ</a>
          <a href="../pages/list_edoc_taseng.php"      class="<?= in_array($_current, ['list_edoc_taseng.php']) ? 'active' : '' ?>">ລາຍຊື່ຕາແສງ</a>
          <a href="../pages/list_edoc_village.php"      class="<?= in_array($_current, ['list_edoc_village.php']) ? 'active' : '' ?>">ລາຍຊື່ບ້ານ</a>
          <a href="../pages/list_edoc_doc_type.php"     class="<?= in_array($_current, ['list_edoc_doc_type.php']) ? 'active' : '' ?>">ປະເພດເອກະສານ</a>
          <a href="../pages/list_edoc_doc_item.php"     class="<?= in_array($_current, ['list_edoc_doc_item.php']) ? 'active' : '' ?>">ລາຍການເອກະສານ</a>
          <a href="../pages/list_edoc_fee_type.php"     class="<?= in_array($_current, ['list_edoc_fee_type.php']) ? 'active' : '' ?>">ປະເພດລາຍຮັບ</a>
          <a href="../pages/list_edoc_expense_type.php" class="<?= in_array($_current, ['list_edoc_expense_type.php']) ? 'active' : '' ?>">ປະເພດລາຍຈ່າຍ</a>
        </div>
      </div>

      <!-- 3. ເຄື່ອນໄຫວການເງິນ -->
      <a class="sidebar-link has-children <?= in_array($_current, ['list_edoc_document.php']) ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#nav-transaction" role="button">
        <i class="fas fa-exchange-alt"></i>
        <span>ບໍລິການເອກະສານ</span>
        <i class="fas fa-chevron-right sidebar-arrow"></i>
      </a>
      <div class="collapse <?= in_array($_current, ['list_edoc_document.php']) ? 'show' : '' ?>" id="nav-transaction">
        <div class="sidebar-sub">
          <a href="../pages/list_edoc_document.php?status=SUBMITTED" class="<?= $_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'SUBMITTED' ? 'active' : '' ?>">ລາຍການ ສະເໜີຂໍເອກະສານ</a>
          <a href="../pages/list_edoc_document.php?status=PENDING_APPROVAL" class="<?= $_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'PENDING_APPROVAL' ? 'active' : '' ?>">ລາຍການ ເອກະສານລໍຖ້າອະນຸມັດ</a>
          <a href="../pages/list_edoc_document.php?status=ISSUED" class="<?= $_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'ISSUED' ? 'active' : '' ?>">ລາຍການ ເອກະສານລໍຖ້າຊຳລະຄ່າທຳນຽມ</a>
          <a href="../pages/list_edoc_document.php?status=HANDOVER" class="<?= $_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'HANDOVER' ? 'active' : '' ?>">ລາຍການ ເອກະສານລໍຖ້າສົ່ງມອບ</a>
          <a href="../pages/list_edoc_document.php" class="<?= $_current === 'list_edoc_document.php' && !isset($_GET['status']) ? 'active' : '' ?>">ລາຍການເອກະສານ ບໍລິການເພີ້່ມ</a>
          <a href="../pages/list_edoc_document.php?status=PAID" class="<?= $_current === 'list_edoc_document.php' && ($_GET['status'] ?? '') === 'PAID' ? 'active' : '' ?>">ລາຍການ ຮັບເງິນ</a>
          <a href="#">ລາຍງານ ການເກັບລາຍຮັບ</a>
          <a href="#">ລາຍງານການບໍລິການເອກະສານ</a>
          <a href="#">ລາຍງານສະຖິຕິເອກະສານ</a>
        </div>
      </div>

      <!-- 5. ລາຍງານໂຄງການ -->
      <a class="sidebar-link has-children collapsed" data-bs-toggle="collapse" href="#nav-report-proj" role="button">
        <i class="fas fa-chart-bar"></i>
        <span>ການຂຶ້ນແຜນ ງົບປະມານ</span>
        <i class="fas fa-chevron-right sidebar-arrow"></i>
      </a>
	  <div class="collapse" id="nav-report-proj">
        <div class="sidebar-sub">
		      <a href="../pages/Type_of_income_State_budget.php">ປະເພດລາຍຮັບ ງົບປະມານແຫ່ງລັດ</a>
          <a href="../pages/Expenditure_type_State_budget.php">ປະເພດລາຍຈ່າຍ ງົບປະມານແຫ່ງລັດ</a>
          <a href="#">ແຜນຄາດຄະເນ ລາຍຮັບງົບປະມານ</a>
          <a href="#">ແຜນຄາດຄະເນ ລາຍຈ່າຍງົບປະມານ</a>
        </div>
      </div>

      <!-- 6. ລາຍງານງົບການເງິນ -->
      <a class="sidebar-link has-children collapsed" data-bs-toggle="collapse" href="#nav-report-fin" role="button">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>ບັນຊີງົບປະມານ</span>
        <i class="fas fa-chevron-right sidebar-arrow"></i>
      </a>
      <div class="collapse" id="nav-report-fin">
        <div class="sidebar-sub">
          <a href="../pages/Budget_revenue_records.php">ລາຍການບັນທຶກ ລາຍຮັບງົບປະມານ</a>
          <a href="#">ລາຍການບັນທຶກ ລາຍຈ່າຍງົບປະມານ</a>
          <a href="#">ລາຍງານ ລາຍຮັບງົບປະມານ</a>
          <a href="#">ລາຍງານ ລາຍຈ່າຍງົບປະມານ</a>
          <a href="#">ລາຍງານສັງລວມ</a>
        </div>
      </div>

      <!-- 8. ລະບົບ -->
      <a class="sidebar-link has-children <?= in_array($_current, ['list_office.php', 'list_users.php', 'list_exchange_rates.php']) ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#nav-system" role="button">
        <i class="fas fa-cogs"></i>
        <span>ລະບົບ</span>
        <i class="fas fa-chevron-right sidebar-arrow"></i>
      </a>
      <div class="collapse <?= in_array($_current, ['list_office.php', 'list_users.php', 'list_exchange_rates.php']) ? 'show' : '' ?>" id="nav-system">
        <div class="sidebar-sub">
          <a href="pages/list_office.php" class="<?= $_current === 'list_office.php' ? 'active' : '' ?>">ຂໍ້ມູນສຳນັກງານ...</a>
          <a href="#">ລາຍການຜູ້ໃຊ້ລະບົບ</a>
          <a href="#">ການປ່ຽນລະຫັດຜ່ານ</a>
          <a href="#">ປິດບັນຊີ...</a>
          <hr style="border-color: rgba(255,255,255,0.1); margin: 5px 15px;">
          <a href="#">ລາຍການພະນັກງານ...</a>
          <a href="/logout.php">ອອກຈາກໂປຣແກຣມ</a>
        </div>
      </div>

      <!-- 9. ຊ່ວຍແນະນຳ -->
      <a href="#" class="sidebar-link">
        <i class="fas fa-question-circle"></i>
        <span>ຊ່ວຍແນະນຳ</span>
      </a>

    </nav>
  </aside>

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ===============================
       MAIN CONTENT AREA
       =============================== -->
  <div class="apims-main">

    <!-- Top Bar -->
    <header class="apims-topbar" style="border-bottom: 1px solid #e5e7eb; padding: 0 1.5rem;">
      <div class="topbar-left" style="display: flex; align-items: center; gap: 15px;">
        <button class="sidebar-toggle" id="sidebarToggle" title="ເປີດ/ປິດ Sidebar" style="color: #64748b;">
          <i class="fas fa-bars"></i>
        </button>
        <div style="color: var(--primary); font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-building"></i>
          <span><?= htmlspecialchars(empty($_SESSION['office_name']) ? 'APPAC (ADB Fund)' : $_SESSION['office_name']) ?></span>
          <?php if (!empty($_SESSION['is_view_only'])): ?>
            <span class="badge bg-warning text-dark" style="font-size: 0.75rem; margin-left: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
              <i class="fas fa-eye"></i> View-Only
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">
        <!-- Date Time Badge -->
        <div class="d-none d-md-flex align-items-center" style="background: #f8fafc; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; color: #475569; gap: 10px; border: 1px solid #e2e8f0;">
          <span><i class="far fa-calendar-alt text-secondary"></i> <span id="currentDateLao"></span></span>
          <span style="color: #cbd5e1;">|</span>
          <span><i class="far fa-clock text-secondary"></i> <span id="currentTimeLao"></span></span>
        </div>

        <!-- User Profile Area -->
        <div style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: background 0.2s;" onMouseOver="this.style.background='#f8fafc'" onMouseOut="this.style.background='transparent'">
          <?php $first_letter = mb_substr($_username, 0, 1, "utf-8"); ?>
          <div style="width: 36px; height: 36px; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; box-shadow: 0 2px 4px rgba(var(--primary-rgb), 0.2);">
             <?= mb_strtoupper($first_letter, "utf-8") ?>
          </div>
          <div style="display: flex; flex-direction: column; line-height: 1.2;">
            <span style="font-weight: 700; font-size: 0.9rem; color: #1e293b;"><?= $_username ?></span>
            <span style="font-size: 0.65rem; background: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 10px; font-weight: 600; align-self: flex-start; margin-top: 3px;">User</span>
          </div>
        </div>

        <!-- Logout Button -->
        <button class="btn-topbar-logout" onClick="confirmLogout()" title="ອອກລະບົບ" style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; margin-left: 5px;">
          <i class="fas fa-sign-out-alt"></i>
        </button>
      </div>
    </header>

    <!-- Page content starts -->
    <main class="apims-content">
