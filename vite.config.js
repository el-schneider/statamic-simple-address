import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'

// Statamic externals plugin - treats Vue and @statamic/cms as externals
function statamicExternals() {
  return {
    name: 'statamic-externals',
    enforce: 'pre',

    resolveId(id) {
      if (id === 'vue') return '\0vue-external'
      if (id === '@statamic/cms') return '\0statamic-cms-external'
      if (id === '@statamic/cms/ui') return '\0statamic-cms-ui-external'
      return null
    },

    load(id) {
      if (id === '\0vue-external') {
        // Export all Vue exports dynamically - the window.Vue object contains everything
        return `
          const Vue = window.Vue;
          export default Vue;
          
          // Re-export everything from Vue
          export const {
            // Reactivity
            ref, computed, watch, watchEffect, reactive, readonly, shallowRef, shallowReactive,
            triggerRef, customRef, markRaw, toRaw, isRef, isReactive, isReadonly, isProxy,
            toRef, toRefs, unref,
            
            // Lifecycle
            onMounted, onBeforeMount, onUnmounted, onBeforeUnmount,
            onUpdated, onBeforeUpdate, onActivated, onDeactivated,
            onErrorCaptured, onRenderTracked, onRenderTriggered,
            
            // Component
            defineComponent, defineAsyncComponent, getCurrentInstance,
            h, createVNode, cloneVNode, mergeProps, isVNode,
            
            // Template compilation helpers
            openBlock, createBlock, createElementBlock, createElementVNode, createTextVNode,
            createCommentVNode, createStaticVNode, resolveComponent, resolveDirective,
            resolveDynamicComponent, withDirectives, withModifiers, withKeys, withCtx,
            renderList, renderSlot, toHandlers, Fragment, Teleport, Suspense, KeepAlive,
            
            // Misc
            nextTick, inject, provide, defineProps, defineEmits, defineExpose, defineOptions,
            defineSlots, defineModel, withDefaults, useSlots, useAttrs,
            toDisplayString, camelize, capitalize, normalizeClass, normalizeStyle, normalizeProps,
            guardReactiveProps, createRenderer, createHydrationRenderer, queuePostFlushCb, warn,
            effectScope, getCurrentScope, onScopeDispose, useCssVars, useId, useModel,
            useCssModule, useTransitionState
          } = Vue;
        `
      }
      if (id === '\0statamic-cms-external') {
        return `
          const core = window.__STATAMIC__.core;
          export const { Fieldtype, IndexFieldtype, FieldtypeMixin, HasActionsMixin, HasInputOptionsMixin, HasPreferencesMixin, IndexFieldtypeMixin, InlineEditForm, DateFormatter, ItemActions, RelatedItem, RestoreRevision, RevisionHistory, RevisionPreview, SaveButtonOptions, SortableList, requireElevatedSession, requireElevatedSessionIf, clone, deepClone, resetValuesFromResponse } = core;
        `
      }
      if (id === '\0statamic-cms-ui-external') {
        return `
          const ui = window.__STATAMIC__.ui;
          export const { Alert, AuthCard, Avatar, Badge, Button, ButtonGroup, Calendar, Card, CardList, CardListItem, CardPanel, CharacterCounter, Checkbox, CheckboxGroup, CodeEditor, Combobox, CommandPaletteItem, ConfirmationModal, Context, ContextFooter, ContextHeader, ContextItem, ContextLabel, ContextMenu, ContextSeparator, CreateForm, DatePicker, DateRangePicker, Description, DocsCallout, DragHandle, Dropdown, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, DropdownFooter, DropdownHeader, Editable, ErrorMessage, EmptyStateItem, EmptyStateMenu, Field, Header, Heading, HoverCard, Icon, Input, InputGroup, InputGroupAppend, InputGroupPrepend, Label, Listing, ListingCustomizeColumns, ListingFilters, ListingHeaderCell, ListingPagination, ListingPresets, ListingPresetTrigger, ListingRowActions, ListingSearch, ListingTable, ListingTableBody, ListingTableHead, ListingToggleAll, LivePreview, LivePreviewPopout, Modal, ModalClose, ModalTitle, Pagination, Panel, PanelFooter, PanelHeader, Popover, PublishComponents, PublishContainer, publishContextKey, injectPublishContext, PublishField, PublishFields, PublishFieldsProvider, PublishForm, PublishLocalizations, PublishSections, PublishTabs, Radio, RadioGroup, Select, Separator, Slider, Skeleton, SplitterGroup, SplitterPanel, SplitterResizeHandle, StatusIndicator, Subheading, Switch, TabContent, Stack, StackClose, StackHeader, StackFooter, StackContent, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows, TabList, TabProvider, Tabs, TabTrigger, Textarea, TimePicker, ToggleGroup, ToggleItem, Widget, registerIconSet, registerIconSetFromStrings } = ui;
        `
      }
      return null
    },

    configResolved(resolvedConfig) {
      resolvedConfig.build.rollupOptions.plugins = resolvedConfig.build.rollupOptions.plugins || []
      resolvedConfig.build.rollupOptions.plugins.push({
        name: 'statamic-externals-render',
        renderChunk(code) {
          // Handle mixed imports: import Default, { named } from 'vue'
          code = code.replace(
            /import\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*,\s*(\{[^}]+\})\s+from\s+['"]vue['"];?/g,
            'const $1 = window.Vue;\nconst $2 = window.Vue;',
          )

          // Handle remaining Vue imports
          return code.replace(/import\s+(.+?)\s+from\s+['"]vue['"];?/g, 'const $1 = window.Vue;')
        },
      })
    },
  }
}

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/simple-address.js'],
      publicDirectory: 'resources/dist',
    }),
    statamicExternals(),
    vue(),
  ],
})
