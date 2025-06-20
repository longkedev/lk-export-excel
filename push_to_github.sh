#!/bin/bash

echo "🚀 推送 lkExportExcel 项目到 GitHub..."

# 确保GitHub远程仓库已添加
git remote add github https://github.com/longkedev/lk-export-excel.git 2>/dev/null || echo "GitHub远程仓库已存在"

# 查看远程仓库
echo "📡 当前远程仓库："
git remote -v

# 推送到GitHub
echo "⬆️  推送到GitHub..."
git push github main

if [ $? -eq 0 ]; then
    echo "✅ 成功推送到GitHub！"
    echo "📍 GitHub仓库地址: https://github.com/longkedev/lk-export-excel"
else
    echo "❌ 推送失败，请检查网络连接或GitHub认证"
    echo "💡 提示："
    echo "   1. 检查网络连接"
    echo "   2. 确认GitHub账户权限"
    echo "   3. 如需要，请先运行 'git config --global http.version HTTP/1.1'"
fi 