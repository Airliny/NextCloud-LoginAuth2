# SPDX-FileCopyrightText: 2026 Airliny
# SPDX-License-Identifier: AGPL-3.0-or-later

APP_NAME=user_airliny
VERSION=1.0.0

# 打包为可直接安装的 tar.gz：
#   make dist   ->  ../user_airliny-1.0.0.tar.gz
dist: lint
	rm -rf build && mkdir -p build/$(APP_NAME)
	cp -r appinfo css js lib l10n templates build/$(APP_NAME)/
	cp composer.json COPYING README.md CHANGELOG.md build/$(APP_NAME)/ 2>/dev/null || true
	tar -czf $(APP_NAME)-$(VERSION).tar.gz -C build $(APP_NAME)
	@echo "✔ 打包完成: $(APP_NAME)-$(VERSION).tar.gz"

lint:
	@find lib appinfo templates -name '*.php' | while read f; do php -l "$$f" > /dev/null || exit 1; done
	@echo "✔ PHP 语法检查通过"

clean:
	rm -rf build *.tar.gz

.PHONY: dist lint clean
