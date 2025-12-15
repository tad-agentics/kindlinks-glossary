#!/bin/bash

echo "🚀 Setting up Kindlinks Glossary Git Repository"
echo "================================================"
echo ""

# Initialize git if not already initialized
if [ ! -d .git ]; then
    echo "📦 Initializing Git repository..."
    git init
else
    echo "✓ Git already initialized"
fi

# Add all files
echo "📝 Adding files..."
git add .

# Create initial commit
echo "💾 Creating initial commit..."
git commit -m "Initial commit: Kindlinks Auto Glossary v2.1.0

Features:
- WordPress plugin for automatic keyword highlighting
- Kindle-style tooltips with Tippy.js (bundled locally)
- Admin interface for term management
- REST API for term synchronization
- WordPress post category filtering
- Analytics and click tracking
- Import/Export functionality
- Shortcodes support
- Performance optimized for 50k+ words content
- Accessibility compliant (WCAG 2.1 AA)
- WordPress standards compliant
- Security hardened with SQL injection prevention

Technical:
- PHP 7.4+
- WordPress 5.0+
- No external CDN dependencies
- Client-side processing with JavaScript TreeWalker API
- Transient caching for performance
- Case-insensitive keyword matching"

# Add remote (HTTPS)
echo "🔗 Adding remote repository (HTTPS)..."
git remote add origin https://github.com/tad-agentics/kindlinks-glossary.git

# Set main branch
echo "🌿 Setting up main branch..."
git branch -M main

# Push to GitHub
echo "⬆️  Pushing to GitHub..."
git push -u origin main

echo ""
echo "✅ Done! Repository is now on GitHub"
echo "🔗 https://github.com/tad-agentics/kindlinks-glossary"




