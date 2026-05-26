## 1. Pré-requisito

- [x] 1.1 Confirmar que `solid-dry-refactor` está aplicado (Settings_Repository, Settings_Sanitizer, Login_URLs existem)

## 2. Composer + tooling

- [x] 2.1 Criar `composer.json` com `name`, `type: wordpress-plugin`, `require: php >=7.4`, `require-dev: phpunit/phpunit ^9.6, wp-phpunit/wp-phpunit ^6.7, yoast/phpunit-polyfills ^2.0`
- [x] 2.2 Rodar `composer install` (local OU via container — baixar composer.phar se necessário)
- [x] 2.3 Confirmar `vendor/bin/phpunit` existe e responde a `--version`
- [x] 2.4 Criar `.gitignore` (se não existir) com `vendor/`, `tests/tmp/`, `composer.lock` (opcional manter)

## 3. PHPUnit config

- [x] 3.1 Criar `phpunit.xml.dist` na raiz do plugin com: `bootstrap="tests/bootstrap.php"`, testsuite apontando para `tests/integration/`, `colors="true"`, `verbose="true"`
- [x] 3.2 Adicionar `<php>` block com env vars: `WP_TESTS_DIR`, `WP_TESTS_DOMAIN=example.org`, `WP_TESTS_EMAIL=admin@example.org`, `WP_TESTS_TITLE=Test Site`

## 4. Bootstrap

- [x] 4.1 Criar `tests/bootstrap.php` que carrega `WP_TESTS_DIR . '/includes/functions.php'`
- [x] 4.2 Registrar hook `muplugins_loaded` carregando `dirname(__DIR__) . '/loomi-studio-setup.php'`
- [x] 4.3 Carregar `WP_TESTS_DIR . '/includes/bootstrap.php'` ao final
- [x] 4.4 Carregar `tests/helpers/BaseTestCase.php`

## 5. Test database setup

- [x] 5.1 Criar `tests/setup-db.sh` que executa `docker exec loomi-clean-db mysql -uroot -prootpass -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"`
- [x] 5.2 Garantir que o usuário `wordpress` tem grants no schema `wordpress_test`
- [x] 5.3 Criar `wp-tests-config.php` template (baseado no de WP-PHPUnit) apontando para `wordpress_test`, mesmo MySQL host/credentials

## 6. BaseTestCase

- [x] 6.1 Criar `tests/helpers/BaseTestCase.php` com `Loomi_TestCase extends WP_UnitTestCase`
- [x] 6.2 `setUp()` chama `parent::setUp()`, `Settings_Repository::clear_cache()`, `delete_option(Plugin::OPTION_KEY)`
- [x] 6.3 Método protegido `set_settings(array $overrides)` que aplica defaults + merge + clear_cache
- [x] 6.4 Método protegido `login_as(string $role): int` usando `$this->factory->user->create`

## 7. SvgSanitizerTest

- [x] 7.1 Criar `tests/integration/SvgSanitizerTest.php` extending `Loomi_TestCase`
- [x] 7.2 `test_clean_svg_passes_through`
- [x] 7.3 `test_script_tag_removed`
- [x] 7.4 `test_onload_attribute_removed`
- [x] 7.5 `test_javascript_href_removed`
- [x] 7.6 `test_style_with_url_javascript_removed`
- [x] 7.7 `test_xxe_neutered`
- [x] 7.8 `test_billion_laughs_blocked`
- [x] 7.9 `test_foreign_object_removed`
- [x] 7.10 `test_data_svg_href_removed`
- [x] 7.11 `test_data_png_href_kept`
- [x] 7.12 `test_malformed_xml_rejects_with_error`

## 8. DuplicatorTest

- [x] 8.1 Criar `tests/integration/DuplicatorTest.php`
- [x] 8.2 `test_duplicate_creates_draft_with_suffixed_title`
- [x] 8.3 `test_duplicate_preserves_content_and_excerpt`
- [x] 8.4 `test_duplicate_copies_thumbnail_id_meta`
- [x] 8.5 `test_duplicate_copies_acf_like_meta_strings_and_arrays`
- [x] 8.6 `test_duplicate_copies_taxonomies`
- [x] 8.7 `test_duplicate_skips_edit_lock_meta`
- [x] 8.8 `test_source_post_unchanged_after_duplicate`
- [x] 8.9 `test_handler_dies_without_valid_nonce`
- [x] 8.10 `test_handler_dies_without_edit_post_capability`

## 9. LoginSlugRoutingTest

- [x] 9.1 Criar `tests/integration/LoginSlugRoutingTest.php`
- [x] 9.2 `test_gate_404s_unauthenticated_get`
- [x] 9.3 `test_gate_404s_reauth_query`
- [x] 9.4 `test_gate_allows_logout_action`
- [x] 9.5 `test_gate_allows_lostpassword_action`
- [x] 9.6 `test_gate_allows_post_method`
- [x] 9.7 `test_gate_allows_when_logged_in`
- [x] 9.8 `test_maybe_serve_login_matches_slug_path`
- [x] 9.9 `test_render_not_found_uses_theme_404_when_present`
- [x] 9.10 `test_render_not_found_falls_back_to_wp_die`

## 10. LoginUrlsTest

- [x] 10.1 Criar `tests/integration/LoginUrlsTest.php`
- [x] 10.2 `test_build_no_args_returns_slug_url`
- [x] 10.3 `test_build_with_action`
- [x] 10.4 `test_build_with_redirect_to_encodes_value`
- [x] 10.5 `test_build_with_reauth_flag`
- [x] 10.6 `test_build_filters_null_extras`
- [x] 10.7 `test_filter_login_url_replaces_wp_login`
- [x] 10.8 `test_filter_logout_url_includes_nonce`

## 11. RoleTest

- [x] 11.1 Criar `tests/integration/RoleTest.php`
- [x] 11.2 `test_role_created_on_activation`
- [x] 11.3 `test_role_has_expected_editor_capabilities`
- [x] 11.4 `test_role_lacks_all_forbidden_capabilities` (loop por FORBIDDEN_CAPS)
- [x] 11.5 `test_role_hidden_from_editable_roles_when_toggle_off`
- [x] 11.6 `test_uninstall_removes_role_and_reassigns_users_to_subscriber`

## 12. HideMenusTest

- [x] 12.1 Criar `tests/integration/HideMenusTest.php`
- [x] 12.2 `test_toggle_off_keeps_all_menus_visible`
- [x] 12.3 `test_editor_loses_selected_menus_when_toggle_on`
- [x] 12.4 `test_admin_always_sees_all_menus`
- [x] 12.5 `test_dashboard_index_never_hidden_even_if_listed`
- [x] 12.6 `test_blacklisted_options_general_never_hidden`
- [x] 12.7 `test_unknown_menu_slug_in_hidden_list_is_ignored`

## 13. WordfenceCheckTest

- [x] 13.1 Criar `tests/integration/WordfenceCheckTest.php`
- [x] 13.2 `test_get_state_returns_absent_when_file_missing`
- [x] 13.3 `test_get_state_returns_installed_inactive_when_file_present_not_active`
- [x] 13.4 `test_get_state_returns_active_when_in_active_plugins`
- [x] 13.5 `test_notice_renders_for_admin_when_absent`
- [x] 13.6 `test_notice_hidden_for_loomi_client`
- [x] 13.7 `test_install_button_hidden_without_install_plugins_cap`
- [x] 13.8 `test_handler_dies_without_valid_nonce`

## 14. SettingsRepositoryTest

- [x] 14.1 Criar `tests/integration/SettingsRepositoryTest.php`
- [x] 14.2 `test_defaults_returned_when_option_missing`
- [x] 14.3 `test_get_returns_individual_field`
- [x] 14.4 `test_get_bool_coerces_string_false_to_false` (regression do bug original)
- [x] 14.5 `test_get_bool_coerces_string_zero_to_false`
- [x] 14.6 `test_get_bool_returns_true_for_real_bool`
- [x] 14.7 `test_clear_cache_forces_reload`
- [x] 14.8 `test_hidden_menus_default_includes_all_hideable`

## 15. SettingsSanitizerTest

- [x] 15.1 Criar `tests/integration/SettingsSanitizerTest.php`
- [x] 15.2 `test_valid_hex_color_accepted`
- [x] 15.3 `test_invalid_color_keeps_previous_value`
- [x] 15.4 `test_reserved_slug_rejected`
- [x] 15.5 `test_slug_with_spaces_sanitized_to_kebab`
- [x] 15.6 `test_unknown_menu_slug_filtered_out`
- [x] 15.7 `test_blacklisted_menu_slug_filtered_out`
- [x] 15.8 `test_boolean_toggles_coerced_correctly`

## 16. UpdaterTest

- [x] 16.1 Criar `tests/integration/UpdaterTest.php`
- [x] 16.2 `test_offline_endpoint_returns_null_silently`
- [x] 16.3 `test_valid_mock_response_injects_update`
- [x] 16.4 `test_malformed_json_discarded`
- [x] 16.5 `test_untrusted_package_url_rejected`
- [x] 16.6 `test_plugins_api_serves_changelog_from_sections`
- [x] 16.7 `test_transient_cache_respected_within_ttl`

## 17. Runner + scripts

- [x] 17.1 Criar `tests/run.sh` (executable) com lógica: ensure DB, ensure vendor, exec phpunit dentro do container
- [x] 17.2 Adicionar `composer scripts` opcional: `composer test` aliases para `phpunit`
- [x] 17.3 Testar runner end-to-end: rodar todos os 10 test classes e confirmar 0 falhas

## 18. Documentação

- [x] 18.1 Adicionar seção "Running tests" no `README.md` cobrindo: install via composer, setup DB, run all, run single class, link pra docs WP-PHPUnit
- [x] 18.2 Atualizar tabela de recursos do README com linha "Suite de testes (WP-PHPUnit)"
- [x] 18.3 Adicionar entrada "Testes automatizados" no Roadmap como concluído

## 19. Cleanup

- [x] 19.1 Remover `loomi-studio-setup/test-svg-sanitizer.php` (substituído por `SvgSanitizerTest`)
- [x] 19.2 Confirmar que ZIP de produção continua excluindo `tests/`, `vendor/`, `composer.json`, `phpunit.xml.dist`
- [x] 19.3 Verificar paridade: rodar suite + os 13 cenários manuais do solid-dry-refactor; 0 divergências
