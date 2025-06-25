<div class="table-view" style="display: block; overflow-x: auto; border: 2px solid blue;">
    <table class="table" style="width: 100%;">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allRecords as $row): ?>
            <tr style="border: 1px solid #ccc;">
                <td><?php echo htmlspecialchars($row['cfirstname'] . ' ' . $row['clastname']); ?></td>
                <td><?php echo htmlspecialchars($row['cemail']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="table-view" style="display: block; overflow-x: auto;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                <th style="width: 40px;"><input type="checkbox" id="table-select-all"></th>
                                <?php endif; ?>
                                <th style="width: 40px;"></th> <!-- Expand/collapse column -->
                                <th>Name</th>
                                <th>Email</th>
                                <th>Job Title</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recordCount > 0): ?>
                                <?php foreach ($allRecords as $row): ?>
                                <tr>
                                    <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                    <td>
                                        <input type="checkbox" class="table-checkbox" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td class="expand-cell">
                                        <button class="expand-row-btn" data-id="<?php echo htmlspecialchars($row['id']); ?>">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['cfirstname'] . ' ' . $row['clastname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cemail']); ?></td>
                                    <td><?php echo htmlspecialchars($row['job_title'] ?? 'Not specified'); ?></td>
                                    <td><?php echo htmlspecialchars($row['client'] ?? 'Not specified'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                                            <?php 
                                                $statusText = '';
                                                switch($row['status']) {
                                                    case 'paperwork_created': $statusText = 'Paperwork Created'; break;
                                                    case 'initiated_agreement_bgv': $statusText = 'Initiated - Agreement, BGV'; break;
                                                    case 'paperwork_closed': $statusText = 'Paperwork Closed'; break;
                                                    case 'started': $statusText = 'Started'; break;
                                                    case 'client_hold': $statusText = 'Client - Hold'; break;
                                                    case 'client_dropped': $statusText = 'Client - Dropped'; break;
                                                    case 'backout': $statusText = 'Backout'; break;
                                                    default: $statusText = ucfirst(str_replace('_', ' ', $row['status']));
                                                }
                                                echo htmlspecialchars($statusText);
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="record-actions">
                                            <button class="action-btn action-preview" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="Preview Details"><i class="fas fa-eye"></i></button>
                                            
                                            <?php if ($row['status'] == 'paperwork_created' || $userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                            <a href="paperworkedit.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn action-edit" title="Edit Record"><i class="fas fa-edit"></i></a>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn action-history" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="View History"><i class="fas fa-history"></i></button>
                                            
                                            <?php if($userRole === 'Admin' || $userRole === 'Contracts') : ?>
                                            <a href="testexport1.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn action-export" title="Export Record"><i class="fas fa-file-export"></i></a>
                                            <?php endif; ?>
                                            
                                            <?php if ($userRole === "Admin") : ?>
                                            <button class="action-btn action-delete" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="Delete Record"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Expanded Row Content -->
                                <tr class="expanded-content" id="expanded-<?php echo htmlspecialchars($row['id']); ?>" style="display: none;">
                                    <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Contracts') ? 9 : 8; ?>">
                                        <div class="expanded-details">
                                            <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                            <div style="margin-bottom: 16px;">
                                                <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500;">Update Status</label>
                                                <select class="status-dropdown" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-current="<?php echo htmlspecialchars($row['status']); ?>">
                                                    <option value="paperwork_created" <?php if ($row['status'] == 'paperwork_created') echo 'selected'; ?>>Paperwork Created</option>
                                                    <option value="initiated_agreement_bgv" <?php if ($row['status'] == 'initiated_agreement_bgv') echo 'selected'; ?>>Initiated – Agreement, BGV</option>
                                                    <option value="paperwork_closed" <?php if ($row['status'] == 'paperwork_closed') echo 'selected'; ?>>Paperwork Closed</option>
                                                    <option value="started" <?php if ($row['status'] == 'started') echo 'selected'; ?>>Started</option>
                                                    <option value="client_hold" <?php if ($row['status'] == 'client_hold') echo 'selected'; ?>>Client – Hold</option>
                                                    <option value="client_dropped" <?php if ($row['status'] == 'client_dropped') echo 'selected'; ?>>Client – Dropped</option>
                                                    <option value="backout" <?php if ($row['status'] == 'backout') echo 'selected'; ?>>Backout</option>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="tabs">
                                                <div class="tab active" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-tab="basic">Basic Info</div>
                                                <div class="tab" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-tab="employment">Employment</div>
                                                <div class="tab" data-id="<?php echo htmlspecialchars($row['id']); ?>" data-tab="plc">PLC Code</div>
                                            </div>
                                            
                                            <div class="tab-content active" id="basic-<?php echo htmlspecialchars($row['id']); ?>">
                                                <div class="info-grid">
                                                    <div class="info-item">
                                                        <span class="info-label">Mobile Number</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['cmobilenumber'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Home Address</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['chomeaddress'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Work Authorization</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['cwork_authorization_status'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Certifications</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['ccertifications'] ?? 'None'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Overall Experience</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['coverall_experience'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Recent Job Title</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['crecent_job_title'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Candidate Source</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['ccandidate_source'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Work Status</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['cwork_authorization_status'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="tab-content" id="employment-<?php echo htmlspecialchars($row['id']); ?>">
                                                <div class="info-grid">
                                                    <div class="info-item">
                                                        <span class="info-label">Job Title</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['job_title'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Client</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['client'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Client Manager</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['client_manager'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Start Date</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">End Date</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Location</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['project_location'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Business Track</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['business_track'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Term</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['term'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Duration</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($row['duration'] ?? 'Not specified'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="tab-content" id="plc-<?php echo htmlspecialchars($row['id']); ?>">
                                                <div class="plc-section">
                                                    <?php 
                                                    // Fetch PLC code if it exists
                                                    $plcData = getPLCCode($conn, $row['id']);
                                                    ?>
                                                    
                                                    <?php if ($plcData): ?>
                                                    <div class="plc-current">
                                                        <div style="font-weight: 500; margin-bottom: 8px;">Current PLC Code:</div>
                                                        <div class="plc-code"><?php echo htmlspecialchars($plcData['plc_code']); ?></div>
                                                        <div class="plc-meta">
                                                            Last updated: <?php echo date('M d, Y H:i', strtotime($plcData['updated_at'])); ?> by <?php echo htmlspecialchars($plcData['updated_by']); ?>
                                                        </div>
                                                    </div>
                                                    <?php else: ?>
                                                    <div style="color: var(--gray-600); font-style: italic; margin-bottom: 16px;">No PLC code has been assigned yet.</div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($userRole === 'Admin' || $userRole === 'Contracts'): ?>
                                                    <form class="plc-form" id="plc-form-<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="hidden" name="paperwork_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                        <input type="text" name="plc_code" class="plc-input" placeholder="Enter PLC code" value="<?php echo htmlspecialchars($plcData['plc_code'] ?? ''); ?>">
                                                        <button type="submit" class="btn btn-primary save-plc"><i class="fas fa-save"></i> Save</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="record-meta" style="margin-top: 16px; font-size: 12px; color: var(--gray-500);">
                                                Submitted by: <?php echo htmlspecialchars($row['submittedby']); ?> on <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Contracts') ? 9 : 8; ?>" style="text-align: center; padding: 20px;">
                                    No records found.
                                </td>
                                <tr>
                                    <td colspan="<?php echo ($userRole === 'Admin' || $userRole === 'Contracts') ? 9 : 8; ?>" style="text-align: center; padding: 20px;">
                                        No records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>