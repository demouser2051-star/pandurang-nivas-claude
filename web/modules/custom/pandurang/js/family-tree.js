/**
 * @file
 * Expand, collapse and search behaviour for the family tree.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Collapses or expands one person's descendants.
   */
  function setExpanded(node, expanded) {
    const toggle = node.querySelector(':scope > .tree-node__card > .tree-node__toggle');
    if (!toggle) {
      return;
    }
    node.classList.toggle('is-collapsed', !expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }

  /**
   * Reveals a node and every ancestor above it.
   */
  function revealPath(node) {
    let current = node.parentElement;
    while (current && current.closest('[data-pn-tree]')) {
      if (current.classList && current.classList.contains('tree-node')) {
        setExpanded(current, true);
      }
      current = current.parentElement;
    }
  }

  Drupal.behaviors.pandurangFamilyTree = {
    attach(context) {
      once('pn-tree', '[data-pn-tree]', context).forEach((tree) => {
        const nodes = Array.from(tree.querySelectorAll('.tree-node'));

        // Toggling a single branch.
        tree.addEventListener('click', (event) => {
          const toggle = event.target.closest('.tree-node__toggle');
          if (!toggle) {
            return;
          }
          event.preventDefault();
          const node = toggle.closest('.tree-node');
          setExpanded(node, node.classList.contains('is-collapsed'));
        });

        // Whole-tree controls.
        const expandAll = tree.querySelector('[data-pn-tree-expand]');
        const collapseAll = tree.querySelector('[data-pn-tree-collapse]');

        if (expandAll) {
          expandAll.addEventListener('click', () => {
            nodes.forEach((node) => setExpanded(node, true));
          });
        }

        if (collapseAll) {
          collapseAll.addEventListener('click', () => {
            // Keep the root generation visible so the tree never looks empty.
            nodes.forEach((node) => {
              if (node.dataset.depth !== '0') {
                setExpanded(node, false);
              }
            });
          });
        }

        // Name search.
        const search = tree.querySelector('[data-pn-tree-search]');
        const noMatch = tree.querySelector('[data-pn-tree-no-match]');

        if (search) {
          let timer = null;

          search.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
              const term = search.value.trim().toLowerCase();

              if (term === '') {
                nodes.forEach((node) => {
                  node.classList.remove('is-hidden', 'is-match');
                });
                if (noMatch) {
                  noMatch.hidden = true;
                }
                return;
              }

              let hits = 0;
              nodes.forEach((node) => {
                const name = node.querySelector(':scope > .tree-node__card .tree-node__name');
                const spouse = node.querySelector(':scope > .tree-node__card .tree-node__spouse');
                const haystack = [
                  name ? name.textContent : '',
                  spouse ? spouse.textContent : '',
                ].join(' ').toLowerCase();

                const matched = haystack.includes(term);
                node.classList.toggle('is-match', matched);
                node.classList.toggle('is-hidden', !matched);

                if (matched) {
                  hits += 1;
                  revealPath(node);
                  // An ancestor of a match must stay visible to reach it.
                  let parent = node.parentElement.closest('.tree-node');
                  while (parent) {
                    parent.classList.remove('is-hidden');
                    parent = parent.parentElement.closest('.tree-node');
                  }
                }
              });

              if (noMatch) {
                noMatch.hidden = hits > 0;
              }
            }, 180);
          });
        }
      });
    },
  };
})(Drupal, once);
