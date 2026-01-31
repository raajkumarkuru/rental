(($, Drupal, once) => {

  /**
   * Navigation + scrollable right sidebar.
   *
   * Typically, to make a div scrollable you just set overflow-y: scroll. We
   * can't do this for the right sidebar because edit_plus's Change tool works
   * by moving hidden form items from the sidebar and placing them on the page
   * for inline editing. Because of the way Safari handles fixed positioning
   * overflow-y: scroll makes the form items disappear. Let's use JS scrolling as
   * a workaround.
   *
   * @type {{attach(*, *): void}}
   */
  Drupal.behaviors.NpScrollableRightSidebar = {
    attach(context, settings) {
      once('np-scrollable-right-sidebar', '#navigation-plus-right-sidebar', context).forEach(wrapper => {

        const scrollbarTrack = document.createElement('div');
        scrollbarTrack.className = 'np-scrollbar-track';

        const scrollbarThumb = document.createElement('div');
        scrollbarThumb.className = 'np-scrollbar-thumb';
        scrollbarTrack.appendChild(scrollbarThumb);
        wrapper.appendChild(scrollbarTrack);

        let scrollPosition = 0;
        let isDragging = false;
        let startY = 0;
        let startScrollPosition = 0;
        let hideTimeout = null;
        let isHovering = false;

        // There are multiple sidebars within the sidebar wrapper.
        const getVisibleSidebar = () => {
          return wrapper.querySelector('.right-sidebar:not(.navigation-plus-hidden)');
        };

        const updateScrollbar = () => {
          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            scrollbarTrack.style.display = 'none';
            return;
          }

          const wrapperHeight = wrapper.clientHeight;
          const contentHeight = sidebar.scrollHeight;

          // Only show scrollbar if content is scrollable.
          if (contentHeight <= wrapperHeight) {
            scrollbarTrack.style.display = 'none';
            return;
          }

          // Calculate thumb height based on visible ratio.
          const visibleRatio = wrapperHeight / contentHeight;
          const thumbHeight = Math.max(30, wrapperHeight * visibleRatio); // Min 30px height
          scrollbarThumb.style.height = `${thumbHeight}px`;

          // Calculate thumb position based on scroll position.
          const scrollRatio = Math.abs(scrollPosition) / (contentHeight - wrapperHeight);
          const maxThumbTop = wrapperHeight - thumbHeight;
          const thumbTop = scrollRatio * maxThumbTop;
          scrollbarThumb.style.transform = `translateY(${thumbTop}px)`;

          // Show scrollbar.
          scrollbarTrack.style.display = 'block';
          scrollbarTrack.classList.add('visible');

          // Auto-hide after delay if not hovering or dragging.
          if (!isDragging && !isHovering) {
            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
              if (!isDragging && !isHovering) {
                scrollbarTrack.classList.remove('visible');
              }
            }, 1000);
          }
        };

        const setScrollPosition = (newPos) => {
          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            return;
          }

          const wrapperHeight = wrapper.clientHeight;
          const contentHeight = sidebar.scrollHeight;

          const maxScroll = 0; // Can't scroll past the top.
          // Only allow scrolling if content is taller than viewport.
          const minScroll = contentHeight > wrapperHeight
            ? wrapperHeight - contentHeight  // Stop when bottom of content reaches bottom of viewport.
            : 0; // Don't allow scrolling if content fits in viewport.

          scrollPosition = Math.max(Math.min(newPos, maxScroll), minScroll);
          sidebar.style.top = `${scrollPosition}px`;
          updateScrollbar();
        };

        wrapper.addEventListener('wheel', e => {
          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            return;
          }

          const wrapperHeight = wrapper.clientHeight;
          const contentHeight = sidebar.scrollHeight;

          if (contentHeight > wrapperHeight) {
            e.preventDefault();
            e.stopPropagation();
            setScrollPosition(scrollPosition - e.deltaY);
          }
        }, { passive: false });

        let touchStartY = 0;
        wrapper.addEventListener('touchstart', e => {
          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            return;
          }

          touchStartY = e.touches[0].clientY;
        }, { passive: true });

        wrapper.addEventListener('touchmove', e => {
          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            return;
          }

          e.preventDefault();
          const touchDelta = e.touches[0].clientY - touchStartY;
          setScrollPosition(scrollPosition + touchDelta);
          touchStartY = e.touches[0].clientY;
        }, { passive: false });

        // Scrollbar drag.
        scrollbarThumb.addEventListener('mousedown', e => {
          e.preventDefault();
          isDragging = true;
          startY = e.clientY;
          startScrollPosition = scrollPosition;
          document.body.style.userSelect = 'none';
          scrollbarTrack.classList.add('dragging');
        });

        document.addEventListener('mousemove', e => {
          if (!isDragging) {
            return;
          }

          const sidebar = getVisibleSidebar();
          if (!sidebar) {
            return;
          }

          const deltaY = e.clientY - startY;
          const wrapperHeight = wrapper.clientHeight;
          const contentHeight = sidebar.scrollHeight;
          const scrollableDistance = contentHeight - wrapperHeight;
          const thumbHeight = parseFloat(scrollbarThumb.style.height);
          const maxThumbTravel = wrapperHeight - thumbHeight;

          const scrollDelta = (deltaY / maxThumbTravel) * scrollableDistance;
          setScrollPosition(startScrollPosition - scrollDelta);
        });

        document.addEventListener('mouseup', () => {
          if (isDragging) {
            isDragging = false;
            document.body.style.userSelect = '';
            scrollbarTrack.classList.remove('dragging');

            // Start hide timer if not hovering
            if (!isHovering) {
              clearTimeout(hideTimeout);
              hideTimeout = setTimeout(() => {
                if (!isHovering) {
                  scrollbarTrack.classList.remove('visible');
                }
              }, 1000);
            }
          }
        });

        // Track hover state
        scrollbarTrack.addEventListener('mouseenter', () => {
          isHovering = true;
          clearTimeout(hideTimeout);
        });

        scrollbarTrack.addEventListener('mouseleave', () => {
          isHovering = false;
          if (!isDragging) {
            hideTimeout = setTimeout(() => {
              if (!isDragging) {
                scrollbarTrack.classList.remove('visible');
              }
            }, 1000);
          }
        });

        // Click on track to jump to position.
        scrollbarTrack.addEventListener('click', e => {
          if (e.target === scrollbarTrack) {
            const sidebar = getVisibleSidebar();
            if (!sidebar) {
              return;
            }

            const rect = scrollbarTrack.getBoundingClientRect();
            const clickY = e.clientY - rect.top;
            const wrapperHeight = wrapper.clientHeight;
            const contentHeight = sidebar.scrollHeight;
            const scrollableDistance = contentHeight - wrapperHeight;

            const scrollRatio = clickY / wrapperHeight;
            setScrollPosition(-scrollRatio * scrollableDistance);
          }
        });

        // Observer to watch for sidebar visibility changes.
        const observer = new MutationObserver(() => {
          const sidebar = getVisibleSidebar();
          if (sidebar) {
            // Reset scroll position when sidebar changes.
            scrollPosition = 0;
            sidebar.style.top = '0px';

            const wrapperHeight = wrapper.clientHeight;
            const contentHeight = sidebar.scrollHeight;

            // If content fits in viewport, ensure it's positioned at top.
            if (contentHeight <= wrapperHeight) {
              sidebar.style.top = '0px';
              scrollPosition = 0;
            }
          }
          updateScrollbar();
        });

        // Observe changes to class attributes on all .right-sidebar elements.
        wrapper.querySelectorAll('.right-sidebar').forEach(sidebar => {
          observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class'],
          });
        });

        const initialSidebar = getVisibleSidebar();
        if (initialSidebar) {
          initialSidebar.style.top = '0px';
          updateScrollbar();
        }

        window.addEventListener('resize', updateScrollbar);
      });
    }
  };

})(jQuery, Drupal, once);
