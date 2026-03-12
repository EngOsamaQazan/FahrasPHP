<?php
/**
 * Fahras Violation Engine
 * Detects duplicate clients across companies and records violations silently.
 *
 * التاريخ المرجعي حسب المصدر:
 * - جدل/نماء: created_on = تاريخ إنشاء العقد الفعلي (لكل عقد على حدة)
 * - زجل/بسيل/محلي: created_on = تاريخ إنشاء العميل (يُعامل كتاريخ العقد)
 * - في حال غياب created_on يُعتمد sell_date (تاريخ البيع اليدوي)
 * - المقارنة تتم بالترتيب الزمني عبر الشركات
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * الشركات المستثناة من نظام المخالفات.
 */
function getExcludedAccounts(): array {
    return ['قصائد', 'المرجع', 'الناظرين', 'نايف قازان'];
}

function getExcludedAccountIds(): array {
    return [1, 2, 3, 4];
}

function isExcludedAccount($accountName): bool {
    return in_array($accountName, getExcludedAccounts(), true);
}

/**
 * Analyze search results and return recommendation for each client group.
 * Groups results by national_id (or name if no national_id).
 * Compares individual contracts across companies chronologically.
 *
 * @param array $allResults  Combined results from local DB + APIs (one row per contract)
 * @return array  Grouped analysis with per-contract violation detection
 */
function analyzeSearchResults($allResults) {
    global $db;

    $groups = groupClientResults($allResults);
    $analyzed = [];

    foreach ($groups as $key => $entries) {
        $entries = filterEntriesWithContracts($entries);

        if (empty($entries)) continue;

        usort($entries, function($a, $b) {
            $da = strtotime(getEffectiveDate($a) ?? '2099-01-01');
            $db_date = strtotime(getEffectiveDate($b) ?? '2099-01-01');
            return $da - $db_date;
        });

        $first = $entries[0];
        $firstAccount = resolveAccountName($first);

        if (count($entries) <= 1) {
            $onlyStatus    = strtolower(trim($first['status'] ?? ''));
            $onlyRemaining = $first['remaining_amount'] ?? null;
            $onlyFinished  = in_array($onlyStatus, ['منتهي', 'finished', 'completed', 'closed']);
            $onlyCanceled  = in_array($onlyStatus, ['ملغي', 'canceled', 'cancelled']);

            if ($onlyFinished || $onlyCanceled) {
                $analyzed[] = [
                    'recommendation' => 'can_sell',
                    'message'        => _e('Contract finished with') . ' ' . $firstAccount . ' - ' . _e('selling is allowed'),
                    'first_account'  => $firstAccount,
                    'first_phone'    => getAccountPhone($first),
                    'results'        => $entries,
                ];
            } elseif ($onlyRemaining !== null && (float)$onlyRemaining >= 150) {
                $analyzed[] = [
                    'recommendation' => 'cannot_sell',
                    'message'        => _e('Client is active with') . ' ' . $firstAccount . ' - ' . _e('remaining amount exceeds') . ' 150 - ' . _e('selling is not allowed'),
                    'first_account'  => $firstAccount,
                    'first_phone'    => getAccountPhone($first),
                    'results'        => $entries,
                ];
            } elseif ($onlyRemaining !== null && (float)$onlyRemaining < 150) {
                $analyzed[] = [
                    'recommendation' => 'can_sell',
                    'message'        => _e('Remaining amount below threshold with') . ' ' . $firstAccount . ' - ' . _e('selling is allowed'),
                    'first_account'  => $firstAccount,
                    'first_phone'    => getAccountPhone($first),
                    'results'        => $entries,
                ];
            } else {
                if (isExcludedAccount($firstAccount)) {
                    $analyzed[] = [
                        'recommendation' => 'can_sell',
                        'message'        => _e('Client not found with other companies - selling is allowed'),
                        'first_account'  => $firstAccount,
                        'first_phone'    => getAccountPhone($first),
                        'results'        => $entries,
                    ];
                } else {
                    $analyzed[] = [
                        'recommendation' => 'contact_first',
                        'message'        => _e('Contact') . ' ' . $firstAccount . ' ' . _e('to verify client status'),
                        'first_account'  => $firstAccount,
                        'first_phone'    => getAccountPhone($first),
                        'results'        => $entries,
                    ];
                }
            }
            continue;
        }

        $activeAccounts = [];
        $contactAccounts = [];
        $entitledEntry = null;
        $violatingEntry = null;

        foreach ($entries as $entry) {
            $entryAccount = resolveAccountName($entry);
            if (isExcludedAccount($entryAccount)) continue;

            $status    = strtolower(trim($entry['status'] ?? ''));
            $remaining = $entry['remaining_amount'] ?? null;
            $isFinished = in_array($status, ['منتهي', 'finished', 'completed', 'closed']);
            $isCanceled = in_array($status, ['ملغي', 'canceled', 'cancelled']);

            if ($isFinished || $isCanceled) continue;
            if ($remaining !== null && (float)$remaining < 150) continue;

            if ($remaining !== null && (float)$remaining >= 150) {
                $activeAccounts[$entryAccount] = $entry;
            } else {
                $contactAccounts[$entryAccount] = $entry;
            }
        }

        $hasViolation = false;
        $needContact = false;
        $violationMessage = '';
        $contactMessage = '';

        $uniqueActiveNames = array_keys($activeAccounts);
        $uniqueContactNames = array_keys($contactAccounts);

        if (count($uniqueActiveNames) >= 2) {
            $hasViolation = true;
            $firstActive = reset($activeAccounts);
            $entitledEntry = $firstActive;
            $lastActive = end($activeAccounts);
            $violatingEntry = $lastActive;
            $companyList = implode(' و ', $uniqueActiveNames);
            $violationMessage = _e('Client is active with') . ' ' . $companyList . ' - ' . _e('remaining amount exceeds') . ' 150 - ' . _e('selling is not allowed');
        } elseif (count($uniqueActiveNames) === 1 && count($uniqueContactNames) >= 1) {
            $allNames = array_merge($uniqueActiveNames, $uniqueContactNames);
            if (count(array_unique($allNames)) >= 2) {
                $needContact = true;
                $companyList = implode(' و ', array_unique($allNames));
                $contactMessage = _e('Client is active with') . ' ' . $companyList . ' - ' . _e('Contact to verify');
            }
        } elseif (count($uniqueContactNames) >= 2) {
            $needContact = true;
            $companyList = implode(' و ', $uniqueContactNames);
            $contactMessage = _e('Client is active with') . ' ' . $companyList . ' - ' . _e('Contact to verify');
        }

        if ($hasViolation) {
            if ($entitledEntry && $violatingEntry) {
                $entitledAccount = resolveAccountName($entitledEntry);
                $violatingAccount = resolveAccountName($violatingEntry);
                $clientName = $entitledEntry['name'] ?? $violatingEntry['name'] ?? '';
                $nationalId = $entitledEntry['national_id'] ?? $violatingEntry['national_id'] ?? '';

                recordViolationFromData(
                    $clientName, $nationalId,
                    (string)$entitledAccount, $entitledEntry['_source'] ?? 'local',
                    getEffectiveDate($entitledEntry),
                    $entitledEntry['remaining_amount'] ?? null,
                    $entitledEntry['status'] ?? null,
                    (string)$violatingAccount, $violatingEntry['_source'] ?? 'local',
                    getEffectiveDate($violatingEntry)
                );
            }

            $analyzed[] = [
                'recommendation' => 'cannot_sell',
                'message'        => $violationMessage,
                'first_account'  => $firstAccount,
                'first_phone'    => getAccountPhone($first),
                'results'        => $entries,
            ];
        } elseif ($needContact) {
            $analyzed[] = [
                'recommendation' => 'contact_first',
                'message'        => $contactMessage,
                'first_account'  => $firstAccount,
                'first_phone'    => getAccountPhone($first),
                'results'        => $entries,
            ];
        } else {
            $allExcluded = true;
            foreach ($entries as $chk) {
                if (!isExcludedAccount(resolveAccountName($chk))) { $allExcluded = false; break; }
            }

            if ($allExcluded) {
                $analyzed[] = [
                    'recommendation' => 'can_sell',
                    'message'        => _e('Client not found with other companies - selling is allowed'),
                    'first_account'  => $firstAccount,
                    'first_phone'    => getAccountPhone($first),
                    'results'        => $entries,
                ];
            } else {
                $firstNonExcluded = $first;
                foreach ($entries as $chk) {
                    if (!isExcludedAccount(resolveAccountName($chk))) { $firstNonExcluded = $chk; break; }
                }
                $neAccount    = resolveAccountName($firstNonExcluded);
                $neStatus     = strtolower(trim($firstNonExcluded['status'] ?? ''));
                $neRemaining  = $firstNonExcluded['remaining_amount'] ?? null;
                $neFinished   = in_array($neStatus, ['منتهي', 'finished', 'completed', 'closed']);
                $neCanceled   = in_array($neStatus, ['ملغي', 'canceled', 'cancelled']);

                if ($neFinished || $neCanceled) {
                    $analyzed[] = [
                        'recommendation' => 'can_sell',
                        'message'        => _e('Contract finished with') . ' ' . $neAccount . ' - ' . _e('selling is allowed'),
                        'first_account'  => $neAccount,
                        'first_phone'    => getAccountPhone($firstNonExcluded),
                        'results'        => $entries,
                    ];
                } elseif ($neRemaining !== null && (float)$neRemaining >= 150) {
                    $analyzed[] = [
                        'recommendation' => 'cannot_sell',
                        'message'        => _e('Client is active with') . ' ' . $neAccount . ' - ' . _e('remaining amount exceeds') . ' 150 - ' . _e('selling is not allowed'),
                        'first_account'  => $neAccount,
                        'first_phone'    => getAccountPhone($firstNonExcluded),
                        'results'        => $entries,
                    ];
                } elseif ($neRemaining !== null && (float)$neRemaining < 150) {
                    $analyzed[] = [
                        'recommendation' => 'can_sell',
                        'message'        => _e('Remaining amount below threshold with') . ' ' . $neAccount . ' - ' . _e('selling is allowed'),
                        'first_account'  => $neAccount,
                        'first_phone'    => getAccountPhone($firstNonExcluded),
                        'results'        => $entries,
                    ];
                } else {
                    $analyzed[] = [
                        'recommendation' => 'contact_first',
                        'message'        => _e('Contact') . ' ' . $neAccount . ' ' . _e('to verify client status'),
                        'first_account'  => $neAccount,
                        'first_phone'    => getAccountPhone($firstNonExcluded),
                        'results'        => $entries,
                    ];
                }
            }
        }
    }

    return $analyzed;
}

/**
 * Filter out entries with no identifiable data.
 * Keeps entries that have any of: id, created_on, or sell_date.
 * APIs without contract-level data (Zajal/Bseel/local) use sell_date or created_on.
 */
function filterEntriesWithContracts($entries) {
    return array_values(array_filter($entries, function($e) {
        $id = trim((string)($e['id'] ?? ''));
        $createdOn = trim((string)($e['created_on'] ?? ''));
        $sellDate = trim((string)($e['sell_date'] ?? ''));
        return ($id !== '' && $id !== '0') || $createdOn !== '' || $sellDate !== '';
    }));
}

/**
 * Get the effective date for an entry.
 * Priority: created_on → sell_date → null
 */
function getEffectiveDate($entry) {
    $createdOn = trim((string)($entry['created_on'] ?? ''));
    $sellDate  = trim((string)($entry['sell_date'] ?? ''));

    if ($createdOn !== '') return $createdOn;
    if ($sellDate !== '')  return $sellDate;
    return null;
}

/**
 * Group results by national_id (preferred) or by exact name match.
 * Entries without national_id are merged into existing groups if name matches.
 */
function normalizeGroupName($name) {
    $name = str_replace(['أ', 'إ', 'آ'], 'ا', $name);
    $name = str_replace('ة', 'ه', $name);
    $name = str_replace('ى', 'ي', $name);
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = preg_replace('/\s+/u', ' ', $name);
    return $name;
}

function groupClientResults($results) {
    $groups = [];
    $nidIndex = [];
    $nameIndex = [];
    $normNameIndex = [];

    foreach ($results as $r) {
        $nid  = trim($r['national_id'] ?? '');
        $name = trim($r['name'] ?? '');
        $normName = normalizeGroupName($name);
        $groupKey = null;

        if ($nid !== '' && isset($nidIndex[$nid])) {
            $groupKey = $nidIndex[$nid];
        }

        if ($groupKey === null && $name !== '' && isset($nameIndex[$name])) {
            $groupKey = $nameIndex[$name];
        }

        if ($groupKey === null && $normName !== '' && isset($normNameIndex[$normName])) {
            $groupKey = $normNameIndex[$normName];
        }

        if ($groupKey === null && $normName !== '') {
            foreach ($normNameIndex as $existingNorm => $existingKey) {
                if (mb_strpos($normName, $existingNorm) !== false || mb_strpos($existingNorm, $normName) !== false) {
                    $groupKey = $existingKey;
                    break;
                }
            }
        }

        if ($groupKey === null) {
            $groupKey = count($groups);
            $groups[$groupKey] = [];
        }

        $groups[$groupKey][] = $r;

        if ($nid !== '') {
            if (isset($nidIndex[$nid]) && $nidIndex[$nid] !== $groupKey) {
                $oldKey = $nidIndex[$nid];
                if (isset($groups[$oldKey]) && $oldKey !== $groupKey) {
                    $groups[$groupKey] = array_merge($groups[$groupKey], $groups[$oldKey]);
                    unset($groups[$oldKey]);
                    foreach ($nidIndex as $k => $v) { if ($v === $oldKey) $nidIndex[$k] = $groupKey; }
                    foreach ($nameIndex as $k => $v) { if ($v === $oldKey) $nameIndex[$k] = $groupKey; }
                    foreach ($normNameIndex as $k => $v) { if ($v === $oldKey) $normNameIndex[$k] = $groupKey; }
                }
            }
            $nidIndex[$nid] = $groupKey;
        }
        if ($name !== '') {
            $nameIndex[$name] = $groupKey;
        }
        if ($normName !== '') {
            $normNameIndex[$normName] = $groupKey;
        }
    }

    return array_values($groups);
}

/**
 * Load all accounts into a static cache (only ~7 rows).
 */
function _getAccountsCache(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    global $db;
    $cache = ['by_id' => [], 'by_name' => []];
    try {
        $stmt = $db->prepare("SELECT * FROM accounts");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $cache['by_id'][(int)$row['id']] = $row;
            $cache['by_name'][$row['name']] = $row;
        }
    } catch (Throwable $e) {}
    return $cache;
}

/**
 * Resolve account name from either local DB id or API string label.
 */
function resolveAccountName($entry) {
    $nameAliases = ['عمار' => 'بسيل'];
    $accounts = _getAccountsCache();

    $acc = $entry['account'] ?? '';
    if (is_numeric($acc) && (int)$acc > 0) {
        $row = $accounts['by_id'][(int)$acc] ?? null;
        $resolved = $row ? $row['name'] : $acc;
        return $nameAliases[$resolved] ?? $resolved;
    }
    if (!empty($acc)) {
        $resolved = (string)$acc;
        return $nameAliases[$resolved] ?? $resolved;
    }

    $sourceMap = [
        'zajal' => 'زجل',
        'jadal' => 'جدل',
        'namaa' => 'نماء',
        'bseel' => 'بسيل',
    ];
    $source = $entry['_source'] ?? '';
    return $sourceMap[$source] ?? (string)$acc;
}

/**
 * Cache remote API results into remote_clients table for future batch scans.
 * Uses batch lookups and multi-row inserts to minimize DB round-trips.
 */
function cacheRemoteResults($results) {
    global $db;

    $remoteEntries = [];
    foreach ($results as $entry) {
        if (!is_array($entry)) continue;
        $source = $entry['_source'] ?? '';
        if ($source === '' || $source === 'local') continue;
        if (empty($entry['name'])) continue;
        $remoteEntries[] = $entry;
    }
    if (empty($remoteEntries)) return;

    $byId = [];
    $byName = [];
    foreach ($remoteEntries as $entry) {
        $source = $entry['_source'];
        $contractId = $entry['id'] ?? $entry['cid'] ?? null;
        if (!empty($contractId) && $contractId !== '0') {
            $byId[] = ['source' => $source, 'remote_id' => $contractId];
        } else {
            $byName[] = ['source' => $source, 'name' => $entry['name']];
        }
    }

    $existingById = [];
    $existingByName = [];

    try {
        if (!empty($byId)) {
            $conds = [];
            $params = [];
            foreach ($byId as $i => $pair) {
                $conds[] = "(source = :s{$i} AND remote_id = :r{$i})";
                $params["s{$i}"] = $pair['source'];
                $params["r{$i}"] = $pair['remote_id'];
            }
            $sql = "SELECT source, remote_id FROM remote_clients WHERE " . implode(' OR ', $conds);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $existingById[$row['source'] . '|' . $row['remote_id']] = true;
            }
        }

        if (!empty($byName)) {
            $conds = [];
            $params = [];
            foreach ($byName as $i => $pair) {
                $conds[] = "(source = :s{$i} AND name = :n{$i})";
                $params["s{$i}"] = $pair['source'];
                $params["n{$i}"] = $pair['name'];
            }
            $sql = "SELECT source, name FROM remote_clients WHERE " . implode(' OR ', $conds);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $existingByName[$row['source'] . '|' . $row['name']] = true;
            }
        }
    } catch (Throwable $e) {}

    $toInsert = [];
    $toUpdate = [];

    foreach ($remoteEntries as $entry) {
        $source = $entry['_source'];
        $contractId = $entry['id'] ?? $entry['cid'] ?? null;
        $hasId = !empty($contractId) && $contractId !== '0';
        $key = $hasId ? ($source . '|' . $contractId) : ($source . '|' . $entry['name']);
        $exists = $hasId ? isset($existingById[$key]) : isset($existingByName[$key]);

        if ($exists) {
            $toUpdate[] = $entry;
        } else {
            $toInsert[] = $entry;
        }
    }

    try {
        if (!empty($toInsert)) {
            $cols = ['source', 'remote_id', 'name', 'party_type', 'national_id', 'sell_date', 'remaining_amount', 'status', 'phone', 'raw_data', 'created_on'];
            $placeholders = [];
            $params = [];
            foreach ($toInsert as $i => $entry) {
                $contractId = $entry['id'] ?? $entry['cid'] ?? null;
                $ph = [];
                foreach ($cols as $c) $ph[] = ":{$c}{$i}";
                $placeholders[] = '(' . implode(',', $ph) . ')';
                $params["source{$i}"] = $entry['_source'];
                $params["remote_id{$i}"] = (!empty($contractId) && $contractId !== '0') ? $contractId : null;
                $params["name{$i}"] = $entry['name'];
                $params["party_type{$i}"] = $entry['party_type'] ?? 'عميل';
                $params["national_id{$i}"] = $entry['national_id'] ?? null;
                $params["sell_date{$i}"] = $entry['sell_date'] ?? null;
                $params["remaining_amount{$i}"] = $entry['remaining_amount'] ?? null;
                $params["status{$i}"] = $entry['status'] ?? null;
                $params["phone{$i}"] = $entry['phone'] ?? null;
                $params["raw_data{$i}"] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                $params["created_on{$i}"] = $entry['created_on'] ?? null;
            }
            $sql = "INSERT INTO remote_clients (" . implode(',', $cols) . ") VALUES " . implode(',', $placeholders);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
    } catch (Throwable $e) {}

    foreach ($toUpdate as $entry) {
        try {
            $source = $entry['_source'];
            $contractId = $entry['id'] ?? $entry['cid'] ?? null;
            $updateSql = "UPDATE remote_clients SET remaining_amount = ?, status = ?, created_on = ?, sell_date = ?, party_type = ?, synced_at = NOW() WHERE source = ?";
            $updateParams = [$entry['remaining_amount'] ?? null, $entry['status'] ?? null, $entry['created_on'] ?? null, $entry['sell_date'] ?? null, $entry['party_type'] ?? 'عميل', $source];
            if (!empty($contractId) && $contractId !== '0') {
                $updateSql .= " AND remote_id = ?";
                $updateParams[] = $contractId;
            } else {
                $updateSql .= " AND name = ?";
                $updateParams[] = $entry['name'];
            }
            $db->run($updateSql, $updateParams);
        } catch (Throwable $e) {}
    }
}

/**
 * Build the contract parties API URL for any source.
 * All 4 APIs already provide party data (HTML format).
 */
function getPartiesApiUrl($entry) {
    $source = $entry['_source'] ?? 'local';
    $id = $entry['id'] ?? '';
    $cid = $entry['cid'] ?? $id;

    if (empty($id) && empty($cid)) return null;

    switch ($source) {
        case 'zajal':
            return 'https://zajal.cc/fahras-parties-api.php?token=354afdf5357c&contract=' . urlencode($id);
        case 'jadal':
            return 'https://jadal.aqssat.co/fahras/relations.php?token=b83ba7a49b72&db=jadal&client=' . urlencode($cid);
        case 'namaa':
            return 'https://jadal.aqssat.co/fahras/relations.php?token=b83ba7a49b72&db=erp&client=' . urlencode($cid);
        case 'bseel':
            return 'https://bseel.com/parties.php?contract=' . urlencode($id);
        default:
            return null;
    }
}

/**
 * Parse party data from HTML table response.
 * Extracts name and national ID from <td> cells.
 */
function parsePartiesFromHtml($html) {
    $parties = [];
    if (empty($html) || strpos($html, '<table') === false) return $parties;

    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $rows = $xpath->query('//tbody/tr');

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 4) continue;

        $name = '';
        $nationalId = '';
        $contractId = '';

        // Typical column order: #, contract_id, name, national_id, phone, job
        if ($cells->length >= 4) {
            $contractId = trim($cells->item(1)->textContent ?? '');
            $nameCell = trim($cells->item(2)->textContent ?? '');
            $name = preg_replace('/\s*\(.*?\)\s*$/', '', $nameCell);
            $nationalId = trim($cells->item(3)->textContent ?? '');
        }

        if (!empty($name)) {
            $parties[] = [
                'name'        => $name,
                'id_number'   => $nationalId,
                'contract_id' => $contractId,
            ];
        }
    }

    return $parties;
}

/**
 * Fetch contract parties for multiple entries in parallel.
 * Supports all sources (Zajal, Jadal, Namaa, Bseel).
 * Handles both JSON and HTML API responses.
 */
function fetchContractParties($entries) {
    $urls = [];
    $clientNames = [];

    foreach ($entries as $idx => $entry) {
        $url = getPartiesApiUrl($entry);
        if (!$url) continue;
        $urls[$idx] = $url;
        $clientNames[$idx] = mb_strtolower(trim($entry['name'] ?? ''));
    }

    if (empty($urls)) return [];

    $responses = curl_multi_load($urls, 5);
    $partyResults = [];

    foreach ($responses as $idx => $resp) {
        if ($resp['error'] || empty($resp['body'])) continue;

        $body = $resp['body'];
        $parties = [];

        $jsonData = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $parties = $jsonData['data'] ?? $jsonData;
        } else {
            $parties = parsePartiesFromHtml($body);
        }

        if (empty($parties) || !is_array($parties)) continue;

        $mainName = $clientNames[$idx] ?? '';
        $otherParties = [];

        foreach ($parties as $p) {
            $pName = trim($p['name'] ?? '');
            $pNid = trim($p['id_number'] ?? $p['national_id'] ?? '');
            if (empty($pName)) continue;
            if (mb_strtolower($pName) === $mainName) continue;

            $otherParties[] = [
                'name'        => $pName,
                'national_id' => $pNid,
                'contract_id' => $p['contract_id'] ?? null,
            ];
        }

        if (!empty($otherParties)) {
            $partyResults[$idx] = $otherParties;
        }
    }

    return $partyResults;
}

/**
 * Check parties (guarantors) across all sources.
 * Returns array of party analysis results.
 */
function checkPartiesForViolations($partyResults, $entries) {
    $partyWarnings = [];

    foreach ($partyResults as $entryIdx => $parties) {
        $entry = $entries[$entryIdx] ?? null;
        if (!$entry) continue;

        $entryAccount = resolveAccountName($entry);
        $entrySource = $entry['_source'] ?? 'local';

        foreach ($parties as $party) {
            $partyName = $party['name'];
            $partyNid = $party['national_id'] ?? '';

            $searchResults = searchPartyAcrossSources($partyName, $partyNid);
            if (empty($searchResults)) continue;

            foreach ($searchResults as $found) {
                $foundAccount = resolveAccountName($found);
                if ($foundAccount === $entryAccount) continue;
                if (isExcludedAccount($foundAccount)) continue;

                $foundStatus = strtolower(trim($found['status'] ?? ''));
                $isFinished = in_array($foundStatus, ['منتهي', 'finished', 'completed', 'closed']);
                $isCanceled = in_array($foundStatus, ['ملغي', 'canceled', 'cancelled']);
                if ($isFinished || $isCanceled) continue;

                $remaining = $found['remaining_amount'] ?? null;
                if ($remaining === null || (float)$remaining < 150) continue;

                $partyWarnings[] = [
                    'party_name'       => $partyName,
                    'party_nid'        => $partyNid,
                    'found_account'    => $foundAccount,
                    'found_source'     => $found['_source'] ?? 'local',
                    'found_remaining'  => $remaining,
                    'violating_account'=> $entryAccount,
                    'violating_source' => $entrySource,
                    'violating_date'   => getEffectiveDate($entry),
                    'found_date'       => getEffectiveDate($found),
                    'found_status'     => $found['status'] ?? '',
                    'has_remaining'    => true,
                ];

                recordViolationFromData(
                    $partyName, $partyNid,
                    (string)$foundAccount, $found['_source'] ?? 'local',
                    getEffectiveDate($found), $remaining, $found['status'] ?? null,
                    (string)$entryAccount, $entrySource,
                    getEffectiveDate($entry)
                );
            }
        }
    }

    return $partyWarnings;
}

/**
 * Search for a party name/national_id across local DB and remote_clients.
 */
function searchPartyAcrossSources($name, $nationalId = '') {
    global $db;
    $results = [];

    try {
        if (!empty($nationalId)) {
            $stmt = $db->prepare("SELECT *, 'local' AS _source FROM clients WHERE national_id = :nid LIMIT 10");
            $stmt->execute(['nid' => $nationalId]);
            $results = array_merge($results, $stmt->fetchAll());

            $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE national_id = :nid LIMIT 10");
            $stmt2->execute(['nid' => $nationalId]);
            $results = array_merge($results, $stmt2->fetchAll());
        }

        if (empty($results) && !empty($name)) {
            $stmt = $db->prepare("SELECT *, 'local' AS _source FROM clients WHERE name = :n LIMIT 10");
            $stmt->execute(['n' => $name]);
            $results = array_merge($results, $stmt->fetchAll());

            $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE name = :n LIMIT 10");
            $stmt2->execute(['n' => $name]);
            $results = array_merge($results, $stmt2->fetchAll());
        }
    } catch (Throwable $e) {}

    return $results;
}

/**
 * Get phone number for the account (from cached accounts table or from entry data).
 */
function getAccountPhone($entry) {
    $accounts = _getAccountsCache();
    $reverseAliases = ['بسيل' => 'عمار'];

    $acc = $entry['account'] ?? '';

    if (is_numeric($acc) && (int)$acc > 0) {
        $row = $accounts['by_id'][(int)$acc] ?? null;
        if ($row) {
            $ph = $row['phone'] ?? $row['mobile'] ?? '';
            if ($ph !== '') return $ph;
        }
    }

    $accountName = (string)$acc;
    if (!empty($accountName)) {
        $row = $accounts['by_name'][$accountName] ?? null;
        if ($row) {
            $ph = $row['phone'] ?? $row['mobile'] ?? '';
            if ($ph !== '') return $ph;
        }
        $altName = $reverseAliases[$accountName] ?? null;
        if ($altName) {
            $row = $accounts['by_name'][$altName] ?? null;
            if ($row) {
                $ph = $row['phone'] ?? $row['mobile'] ?? '';
                if ($ph !== '') return $ph;
            }
        }
    }

    $sourceMap = [
        'zajal' => 'زجل',
        'jadal' => 'جدل',
        'namaa' => 'نماء',
        'bseel' => 'بسيل',
    ];
    $source = $entry['_source'] ?? '';
    if (!empty($source) && isset($sourceMap[$source])) {
        $srcName = $sourceMap[$source];
        $row = $accounts['by_name'][$srcName] ?? null;
        if ($row) {
            $ph = $row['phone'] ?? $row['mobile'] ?? '';
            if ($ph !== '') return $ph;
        }
        $altName = $reverseAliases[$srcName] ?? null;
        if ($altName) {
            $row = $accounts['by_name'][$altName] ?? null;
            if ($row) {
                $ph = $row['phone'] ?? $row['mobile'] ?? '';
                if ($ph !== '') return $ph;
            }
        }
    }

    return $entry['phone'] ?? '';
}

/**
 * Silently check and record violations when a client is added.
 * Called after successful insert - does NOT block or alert the user.
 *
 * @param array $clientData  The inserted client data
 * @param int   $accountId   The account that added the client
 */
function silentViolationCheck($clientData, $accountId) {
    global $db;

    $violatingAccount = $db->get_var('accounts', ['id' => $accountId], ['name']) ?: $accountId;
    if (isExcludedAccount((string)$violatingAccount)) return;

    $name       = trim($clientData['name'] ?? '');
    $nationalId = trim($clientData['national_id'] ?? '');

    if (empty($name) && empty($nationalId)) return;

    $existing = findExistingClients($name, $nationalId, $accountId);
    $existing = filterEntriesWithContracts($existing);

    if (empty($existing)) return;

    usort($existing, function($a, $b) {
        $da = strtotime(getEffectiveDate($a) ?? '2099-01-01');
        $db_date = strtotime(getEffectiveDate($b) ?? '2099-01-01');
        return $da - $db_date;
    });

    $first = $existing[0];
    $firstStatus   = strtolower(trim($first['status'] ?? ''));
    $remaining     = $first['remaining_amount'] ?? null;
    $isFinished    = in_array($firstStatus, ['منتهي', 'finished', 'completed', 'closed']);
    $isCanceled    = in_array($firstStatus, ['ملغي', 'canceled', 'cancelled']);

    if ($isFinished || $isCanceled) return;
    if ($remaining !== null && (float)$remaining < 150) return;
    if ($remaining === null) return;

    $entitledAccount = resolveAccountName($first);
    if (isExcludedAccount((string)$entitledAccount)) return;
    $entitledSource = $first['_source'] ?? 'local';
    $violationMonth = date('Y-m-01');

    $status = 'active';
    if ($remaining !== null && (float)$remaining < 150) {
        $status = 'exempted_below_150';
    }

    $existingViolation = $db->get_count('violations', [
        'national_id'       => $nationalId ?: null,
        'client_name'       => $name,
        'violating_account' => (string)$violatingAccount,
        'violation_month'   => $violationMonth,
    ]);

    if ($existingViolation > 0) return;

    $monthCount = countMonthlyViolations((string)$violatingAccount, $violationMonth);
    $fineAmount = ($monthCount >= 5) ? 40.00 : 20.00;

    try {
        $db->insert('violations', [
            'client_name'       => $name,
            'national_id'       => $nationalId ?: null,
            'entitled_account'  => (string)$entitledAccount,
            'entitled_source'   => $entitledSource,
            'entitled_sell_date'=> getEffectiveDate($first),
            'entitled_remaining'=> $remaining,
            'violating_account' => (string)$violatingAccount,
            'violating_source'  => 'local',
            'violating_sell_date'=> date('Y-m-d H:i:s'),
            'status'            => $status,
            'violation_month'   => $violationMonth,
            'fine_amount'       => $fineAmount,
            'detected_by'       => $_SESSION['user_id'] ?? null,
        ]);
    } catch (Exception $e) {
        // silently fail
    }
}

/**
 * Find existing clients in local DB and remote_clients cache.
 */
function findExistingClients($name, $nationalId, $excludeAccountId = null) {
    global $db;

    $results = [];

    if (!empty($nationalId)) {
        $stmt = $db->prepare("SELECT *, 'local' AS _source FROM clients WHERE national_id = :nid AND account != :acc LIMIT 20");
        $stmt->execute(['nid' => $nationalId, 'acc' => $excludeAccountId ?? 0]);
        $results = array_merge($results, $stmt->fetchAll());

        $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE national_id = :nid LIMIT 20");
        $stmt2->execute(['nid' => $nationalId]);
        $results = array_merge($results, $stmt2->fetchAll());
    }

    if (empty($results) && !empty($name)) {
        $stmt = $db->prepare("SELECT *, 'local' AS _source FROM clients WHERE name = :name AND account != :acc LIMIT 20");
        $stmt->execute(['name' => $name, 'acc' => $excludeAccountId ?? 0]);
        $results = array_merge($results, $stmt->fetchAll());

        $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE name = :name LIMIT 20");
        $stmt2->execute(['name' => $name]);
        $results = array_merge($results, $stmt2->fetchAll());
    }

    return $results;
}

/**
 * Count violations for a given account in a given month.
 */
function countMonthlyViolations($accountName, $month) {
    global $db;
    return $db->get_count('violations', [
        'violating_account' => $accountName,
        'violation_month'   => $month,
    ]);
}

/**
 * Calculate monthly fine for a violating account.
 * First 5 violations: 20 JOD each. 6th and beyond: 40 JOD each.
 */
function calculateMonthlyFine($violationCount) {
    if ($violationCount <= 5) {
        return $violationCount * 20;
    }
    return (5 * 20) + (($violationCount - 5) * 40);
}

/**
 * Batch scan: find all duplicate clients across accounts.
 * Scans local clients vs local clients AND local clients vs remote_clients (APIs).
 */
function batchScanViolations() {
    global $db;

    $newViolations = 0;

    // ─── الجزء 1: مقارنة العملاء المحليين مع بعضهم ───
    $excludedIds = implode(',', getExcludedAccountIds());
    try {
        $stmt = $db->run("
            SELECT c1.id AS first_id, c1.name, c1.national_id, c1.account AS first_account,
                   COALESCE(c1.created_on, c1.sell_date) AS first_date,
                   c1.remaining_amount, c1.status AS first_status,
                   c2.id AS second_id, c2.account AS second_account,
                   COALESCE(c2.created_on, c2.sell_date) AS second_date
            FROM clients c1
            INNER JOIN clients c2
                ON c1.id != c2.id
                AND c1.account != c2.account
                AND (
                    (c1.national_id != '' AND c1.national_id IS NOT NULL AND c1.national_id = c2.national_id)
                    OR (c1.name = c2.name AND (c1.national_id IS NULL OR c1.national_id = ''))
                )
            WHERE COALESCE(c1.created_on, c1.sell_date) < COALESCE(c2.created_on, c2.sell_date)
                AND c1.account NOT IN ($excludedIds) AND c2.account NOT IN ($excludedIds)
            ORDER BY COALESCE(c1.created_on, c1.sell_date) ASC
            LIMIT 5000
        ");
        $duplicates = ($stmt && is_object($stmt)) ? $stmt->fetchAll() : [];

        foreach ($duplicates as $dup) {
            $recorded = recordViolationFromDuplicate(
                $dup['name'], $dup['national_id'],
                $dup['first_account'], 'local', $dup['first_date'],
                $dup['remaining_amount'], $dup['first_status'],
                $dup['second_account'], 'local', $dup['second_date']
            );
            if ($recorded) $newViolations++;
        }
    } catch (Throwable $e) {}

    // ─── الجزء 2: مقارنة العملاء المحليين مع remote_clients (APIs) ───
    // حالة 2أ: العميل المحلي أقدم → الشركة الخارجية مخالفة
    try {
        $stmt2 = $db->run("
            SELECT c.name, c.national_id, c.account AS local_account,
                   COALESCE(c.created_on, c.sell_date) AS local_date,
                   c.remaining_amount, c.status AS local_status,
                   r.source AS remote_source, r.name AS remote_account_label,
                   COALESCE(r.created_on, r.sell_date) AS remote_date,
                   r.remaining_amount AS remote_remaining,
                   r.status AS remote_status
            FROM clients c
            INNER JOIN remote_clients r
                ON (
                    (c.national_id != '' AND c.national_id IS NOT NULL AND c.national_id = r.national_id)
                    OR (c.name = r.name AND (c.national_id IS NULL OR c.national_id = ''))
                )
            WHERE COALESCE(c.created_on, c.sell_date) IS NOT NULL
                AND COALESCE(r.created_on, r.sell_date) IS NOT NULL
                AND COALESCE(c.created_on, c.sell_date) < COALESCE(r.created_on, r.sell_date)
            LIMIT 5000
        ");
        $localFirst = ($stmt2 && is_object($stmt2)) ? $stmt2->fetchAll() : [];

        foreach ($localFirst as $dup) {
            $localAccountName = $db->get_var('accounts', ['id' => $dup['local_account']], ['name']) ?: $dup['local_account'];
            $remoteAccountName = ucfirst($dup['remote_source']);
            $sourceMap = ['zajal' => 'زجل', 'jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل'];
            $remoteAccountName = $sourceMap[$dup['remote_source']] ?? $remoteAccountName;

            if ($localAccountName === $remoteAccountName) continue;

            $recorded = recordViolationFromData(
                $dup['name'], $dup['national_id'],
                (string)$localAccountName, 'local', $dup['local_date'],
                $dup['remaining_amount'], $dup['local_status'],
                (string)$remoteAccountName, $dup['remote_source'], $dup['remote_date']
            );
            if ($recorded) $newViolations++;
        }
    } catch (Throwable $e) {}

    // حالة 2ب: الشركة الخارجية أقدم → العميل المحلي مخالف
    try {
        $stmt3 = $db->run("
            SELECT c.name, c.national_id, c.account AS local_account,
                   COALESCE(c.created_on, c.sell_date) AS local_date,
                   r.source AS remote_source,
                   COALESCE(r.created_on, r.sell_date) AS remote_date,
                   r.remaining_amount AS remote_remaining,
                   r.status AS remote_status
            FROM clients c
            INNER JOIN remote_clients r
                ON (
                    (c.national_id != '' AND c.national_id IS NOT NULL AND c.national_id = r.national_id)
                    OR (c.name = r.name AND (c.national_id IS NULL OR c.national_id = ''))
                )
            WHERE COALESCE(c.created_on, c.sell_date) IS NOT NULL
                AND COALESCE(r.created_on, r.sell_date) IS NOT NULL
                AND COALESCE(r.created_on, r.sell_date) < COALESCE(c.created_on, c.sell_date)
            LIMIT 5000
        ");
        $remoteFirst = ($stmt3 && is_object($stmt3)) ? $stmt3->fetchAll() : [];

        foreach ($remoteFirst as $dup) {
            $localAccountName = $db->get_var('accounts', ['id' => $dup['local_account']], ['name']) ?: $dup['local_account'];
            $sourceMap = ['zajal' => 'زجل', 'jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل'];
            $remoteAccountName = $sourceMap[$dup['remote_source']] ?? ucfirst($dup['remote_source']);

            if ($localAccountName === $remoteAccountName) continue;

            $recorded = recordViolationFromData(
                $dup['name'], $dup['national_id'],
                (string)$remoteAccountName, $dup['remote_source'], $dup['remote_date'],
                $dup['remote_remaining'], $dup['remote_status'],
                (string)$localAccountName, 'local', $dup['local_date']
            );
            if ($recorded) $newViolations++;
        }
    } catch (Throwable $e) {}

    return $newViolations;
}

/**
 * Record a violation from a local-vs-local duplicate pair.
 */
function recordViolationFromDuplicate($name, $nationalId, $firstAccount, $firstSource, $firstDate, $remaining, $firstStatus, $secondAccount, $secondSource, $secondDate) {
    global $db;

    $entitledName  = is_numeric($firstAccount) ? ($db->get_var('accounts', ['id' => (int)$firstAccount], ['name']) ?: $firstAccount) : $firstAccount;
    $violatingName = is_numeric($secondAccount) ? ($db->get_var('accounts', ['id' => (int)$secondAccount], ['name']) ?: $secondAccount) : $secondAccount;

    return recordViolationFromData($name, $nationalId, (string)$entitledName, $firstSource, $firstDate, $remaining, $firstStatus, (string)$violatingName, $secondSource, $secondDate);
}

/**
 * Core violation recording. Checks status, remaining, and deduplication before inserting.
 */
function recordViolationFromData($name, $nationalId, $entitledAccount, $entitledSource, $entitledDate, $remaining, $entitledStatus, $violatingAccount, $violatingSource, $violatingDate, $partyType = 'عميل') {
    global $db;

    if (isExcludedAccount($entitledAccount) || isExcludedAccount($violatingAccount)) return false;

    $status = strtolower(trim($entitledStatus ?? ''));
    $isFinished = in_array($status, ['منتهي', 'finished', 'completed', 'closed']);
    $isCanceled = in_array($status, ['ملغي', 'canceled', 'cancelled']);

    if ($isFinished || $isCanceled) return false;
    if ($remaining !== null && (float)$remaining < 150) return false;
    if ($remaining === null) return false;

    $violationMonth = date('Y-m-01', strtotime($violatingDate ?: 'now'));

    try {
        $exists = $db->get_count('violations', [
            'client_name'       => $name,
            'violating_account' => $violatingAccount,
            'violation_month'   => $violationMonth,
        ]);
        if ($exists > 0) return false;
    } catch (Throwable $e) { return false; }

    $monthCount = countMonthlyViolations($violatingAccount, $violationMonth);
    $fineAmount = ($monthCount >= 5) ? 40.00 : 20.00;

    try {
        $db->insert('violations', [
            'client_name'       => $name,
            'national_id'       => $nationalId ?: null,
            'party_type'        => $partyType ?: 'عميل',
            'entitled_account'  => $entitledAccount,
            'entitled_source'   => $entitledSource,
            'entitled_sell_date'=> $entitledDate,
            'entitled_remaining'=> $remaining,
            'violating_account' => $violatingAccount,
            'violating_source'  => $violatingSource,
            'violating_sell_date'=> $violatingDate,
            'status'            => 'active',
            'violation_month'   => $violationMonth,
            'fine_amount'       => $fineAmount,
            'detected_by'       => $_SESSION['user_id'] ?? null,
        ]);
        return true;
    } catch (Throwable $e) { return false; }
}
