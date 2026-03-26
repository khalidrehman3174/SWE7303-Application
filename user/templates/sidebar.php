    <aside class="sidebar">
        <div class="brand" style="padding: 2rem 1.75rem; font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px;">
            <a href="../index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary);">
                <div style="width: 9px; height: 9px; background: #00d26a; border-radius: 50%; box-shadow: 0 0 8px rgba(0,210,106,0.7); flex-shrink: 0;"></div>
                finpay
            </a>
        </div>
        <nav class="sidebar-menu">
            <a href="index.php" class="nav-link-pro <?php echo (isset($activePage) && $activePage == 'dashboard') ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Dashboard</a>
            <a href="payments.php" class="nav-link-pro <?php echo (isset($activePage) && $activePage == 'payments') ? 'active' : ''; ?>"><i class="fas fa-paper-plane"></i> Payments</a>
            <a href="assets.php" class="nav-link-pro <?php echo (isset($activePage) && $activePage == 'assets') ? 'active' : ''; ?>"><i class="fas fa-wallet"></i> Assets</a>
            <a href="cards.php" class="nav-link-pro <?php echo (isset($activePage) && $activePage == 'cards') ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Cards</a>
            <a href="#" class="nav-link-pro"><i class="fas fa-shield-alt"></i> Security</a>
        </nav>

        <!-- User profile + logout -->
        <div style="margin-top: auto; padding: 1.5rem 1.25rem 2rem;">
            <div class="glass-panel" style="padding: 1rem 1.25rem; display: flex; align-items: center; gap: 14px; margin-bottom: 0.75rem;">
                <?php
                    $display_name = 'User';
                    if (isset($_SESSION['user_id'])) {
                        global $dbc;
                        if (isset($dbc)) {
                            $uid = intval($_SESSION['user_id']);
                            $res = mysqli_query($dbc, "SELECT username FROM users WHERE id = $uid LIMIT 1");
                            if ($res && $row = mysqli_fetch_assoc($res)) {
                                $display_name = htmlspecialchars($row['username']);
                            }
                        }
                    }
                ?>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=00d26a&color=fff&bold=true" alt="<?php echo $display_name; ?>" style="width: 38px; height: 38px; border-radius: 12px; flex-shrink:0;">
                <div style="min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $display_name; ?></div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); font-weight: 500;">Pro Member</div>
                </div>
            </div>
            <a href="../auth/logout.php" style="display: flex; align-items: center; gap: 10px; padding: 0.85rem 1.25rem; border-radius: 14px; color: var(--text-secondary); font-size: 0.92rem; font-weight: 600; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.06)';this.style.color='#ef4444'" onmouseout="this.style.background='transparent';this.style.color='var(--text-secondary)'">
                <i class="fas fa-arrow-right-from-bracket" style="width:20px; text-align:center;"></i> Log out
            </a>
        </div>
    </aside>

