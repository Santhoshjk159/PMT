/**
 * Enhanced Sidebar JavaScript
 * Handles sidebar functionality with smooth animations and responsive behavior
 */

class EnhancedSidebar {
  constructor() {
    this.sidebar = document.querySelector('.sidebar');
    this.sidebarToggler = document.querySelector('.sidebar-toggler');
    this.menuToggler = document.querySelector('.menu-toggler');
    this.main = document.querySelector('.main');
    
    // Configuration
    this.config = {
      sidebarWidth: 280,
      collapsedWidth: 80,
      mobileBreakpoint: 1024,
      animationDuration: 350
    };
    
    // State
    this.isCollapsed = this.sidebar?.classList.contains('collapsed') || false;
    this.isMobileMenuActive = false;
    this.isInitialized = false;
    
    this.init();
  }
  
  init() {
    if (this.isInitialized) return;
    
    this.bindEvents();
    this.setInitialState();
    this.updateMainMargin();
    this.addKeyboardSupport();
    this.addTouchSupport();
    
    this.isInitialized = true;
    
    // Add loaded class for animations
    setTimeout(() => {
      this.sidebar?.classList.add('loaded');
    }, 100);
  }
  
  bindEvents() {
    // Sidebar toggle button
    this.sidebarToggler?.addEventListener('click', (e) => {
      e.preventDefault();
      this.toggleSidebar();
    });
    
    // Mobile menu toggle button
    this.menuToggler?.addEventListener('click', (e) => {
      e.preventDefault();
      this.toggleMobileMenu();
    });
    
    // Window resize handler
    window.addEventListener('resize', this.debounce(() => {
      this.handleResize();
    }, 250));
    
    // Click outside to close mobile menu
    document.addEventListener('click', (e) => {
      if (this.isMobile() && this.isMobileMenuActive && !this.sidebar?.contains(e.target)) {
        this.closeMobileMenu();
      }
    });
    
    // Navigation link interactions
    this.enhanceNavLinks();
  }
  
  toggleSidebar() {
    if (this.isMobile()) return;
    
    this.isCollapsed = !this.isCollapsed;
    this.sidebar?.classList.toggle('collapsed', this.isCollapsed);
    
    this.updateToggleIcon();
    this.updateMainMargin();
    this.saveState();
    
    // Dispatch custom event
    this.dispatchEvent('sidebarToggle', { collapsed: this.isCollapsed });
  }
  
  toggleMobileMenu() {
    if (!this.isMobile()) return;
    
    this.isMobileMenuActive = !this.isMobileMenuActive;
    this.sidebar?.classList.toggle('menu-active', this.isMobileMenuActive);
    
    this.updateMobileMenuIcon();
    this.updateMobileMenuHeight();
    
    // Prevent body scroll when menu is open
    document.body.style.overflow = this.isMobileMenuActive ? 'hidden' : '';
    
    // Dispatch custom event
    this.dispatchEvent('mobileMenuToggle', { active: this.isMobileMenuActive });
  }
  
  closeMobileMenu() {
    if (!this.isMobileMenuActive) return;
    
    this.isMobileMenuActive = false;
    this.sidebar?.classList.remove('menu-active');
    this.updateMobileMenuIcon();
    this.updateMobileMenuHeight();
    document.body.style.overflow = '';
  }
  
  updateToggleIcon() {
    const icon = this.sidebarToggler?.querySelector('i');
    if (!icon) return;
    
    if (this.isCollapsed) {
      icon.className = 'fas fa-chevron-right';
    } else {
      icon.className = 'fas fa-chevron-left';
    }
  }
  
  updateMobileMenuIcon() {
    const icon = this.menuToggler?.querySelector('i');
    if (!icon) return;
    
    if (this.isMobileMenuActive) {
      icon.className = 'fas fa-times';
    } else {
      icon.className = 'fas fa-bars';
    }
  }
  
  updateMobileMenuHeight() {
    if (!this.isMobile()) return;
    
    if (this.isMobileMenuActive) {
      this.sidebar.style.height = `${this.sidebar.scrollHeight}px`;
    } else {
      this.sidebar.style.height = '';
    }
  }
  
  updateMainMargin() {
    if (!this.main) return;
    
    if (this.isMobile()) {
      this.main.style.marginLeft = '0';
    } else {
      const margin = this.isCollapsed ? `${this.config.collapsedWidth}px` : `${this.config.sidebarWidth}px`;
      this.main.style.marginLeft = margin;
    }
  }
  
  handleResize() {
    const wasMobile = this.isMobile();
    
    // Reset mobile menu state when switching to desktop
    if (!this.isMobile() && this.isMobileMenuActive) {
      this.closeMobileMenu();
    }
    
    // Reset collapsed state when switching to mobile
    if (this.isMobile() && this.isCollapsed) {
      this.sidebar?.classList.remove('collapsed');
      this.isCollapsed = false;
    }
    
    this.updateMainMargin();
    this.setInitialState();
    
    // Dispatch resize event
    this.dispatchEvent('sidebarResize', { 
      isMobile: this.isMobile(),
      collapsed: this.isCollapsed 
    });
  }
  
  setInitialState() {
    if (this.isMobile()) {
      this.sidebar.style.height = '';
      this.sidebar?.classList.remove('collapsed');
    } else {
      this.sidebar?.classList.remove('menu-active');
      this.updateToggleIcon();
    }
  }
  
  enhanceNavLinks() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
      // Add ripple effect on click
      link.addEventListener('click', (e) => {
        this.createRipple(e, link);
        
        // Close mobile menu when navigation link is clicked
        if (this.isMobile() && this.isMobileMenuActive) {
          setTimeout(() => this.closeMobileMenu(), 150);
        }
      });
      
      // Add hover sound effect (optional)
      link.addEventListener('mouseenter', () => {
        this.playHoverSound();
      });
    });
  }
  
  createRipple(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
      position: absolute;
      width: ${size}px;
      height: ${size}px;
      left: ${x}px;
      top: ${y}px;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      pointer-events: none;
      transform: scale(0);
      animation: ripple 0.6s linear;
      z-index: 1;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    // Remove ripple after animation
    setTimeout(() => {
      if (ripple.parentNode) {
        ripple.parentNode.removeChild(ripple);
      }
    }, 600);
  }
  
  playHoverSound() {
    // Optional: Add subtle hover sound
    // You can implement this based on your needs
  }
  
  addKeyboardSupport() {
    document.addEventListener('keydown', (e) => {
      // Alt + S to toggle sidebar
      if (e.altKey && e.key === 's') {
        e.preventDefault();
        if (this.isMobile()) {
          this.toggleMobileMenu();
        } else {
          this.toggleSidebar();
        }
      }
      
      // Escape to close mobile menu
      if (e.key === 'Escape' && this.isMobileMenuActive) {
        this.closeMobileMenu();
      }
    });
  }
  
  addTouchSupport() {
    if (!('ontouchstart' in window)) return;
    
    let startX = 0;
    let startY = 0;
    let isSwipeGesture = false;
    
    document.addEventListener('touchstart', (e) => {
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      isSwipeGesture = false;
    });
    
    document.addEventListener('touchmove', (e) => {
      if (!this.isMobile()) return;
      
      const deltaX = e.touches[0].clientX - startX;
      const deltaY = Math.abs(e.touches[0].clientY - startY);
      
      // Detect horizontal swipe
      if (Math.abs(deltaX) > 50 && deltaY < 50) {
        isSwipeGesture = true;
        
        // Swipe right to open menu
        if (deltaX > 0 && !this.isMobileMenuActive && startX < 50) {
          this.toggleMobileMenu();
        }
        // Swipe left to close menu
        else if (deltaX < 0 && this.isMobileMenuActive) {
          this.closeMobileMenu();
        }
      }
    });
  }
  
  saveState() {
    try {
      localStorage.setItem('sidebarCollapsed', this.isCollapsed.toString());
    } catch (e) {
      // Ignore localStorage errors
    }
  }
  
  loadState() {
    try {
      const saved = localStorage.getItem('sidebarCollapsed');
      if (saved !== null && !this.isMobile()) {
        this.isCollapsed = saved === 'true';
        this.sidebar?.classList.toggle('collapsed', this.isCollapsed);
        this.updateToggleIcon();
      }
    } catch (e) {
      // Ignore localStorage errors
    }
  }
  
  isMobile() {
    return window.innerWidth <= this.config.mobileBreakpoint;
  }
  
  dispatchEvent(eventName, detail = {}) {
    const event = new CustomEvent(`sidebar:${eventName}`, { detail });
    document.dispatchEvent(event);
  }
  
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
  
  // Public API methods
  collapse() {
    if (!this.isMobile() && !this.isCollapsed) {
      this.toggleSidebar();
    }
  }
  
  expand() {
    if (!this.isMobile() && this.isCollapsed) {
      this.toggleSidebar();
    }
  }
  
  destroy() {
    // Clean up event listeners and state
    document.body.style.overflow = '';
    window.removeEventListener('resize', this.handleResize);
    this.isInitialized = false;
  }
}

// Add ripple animation CSS
const rippleCSS = `
  @keyframes ripple {
    to {
      transform: scale(2);
      opacity: 0;
    }
  }
`;

// Inject CSS
const style = document.createElement('style');
style.textContent = rippleCSS;
document.head.appendChild(style);

// Initialize sidebar when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = new EnhancedSidebar();
  
  // Load saved state
  sidebar.loadState();
  
  // Make sidebar instance globally available
  window.enhancedSidebar = sidebar;
  
  // Custom event listeners (optional)
  document.addEventListener('sidebar:sidebarToggle', (e) => {
    console.log('Sidebar toggled:', e.detail);
  });
  
  document.addEventListener('sidebar:mobileMenuToggle', (e) => {
    console.log('Mobile menu toggled:', e.detail);
  });
});

// Expose utility functions
window.sidebarUtils = {
  toggle: () => window.enhancedSidebar?.toggleSidebar(),
  collapse: () => window.enhancedSidebar?.collapse(),
  expand: () => window.enhancedSidebar?.expand(),
  isMobile: () => window.enhancedSidebar?.isMobile(),
  isCollapsed: () => window.enhancedSidebar?.isCollapsed
};