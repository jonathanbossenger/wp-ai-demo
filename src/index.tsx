/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ConversationProvider } from './context/ConversationProvider';
import { ChatApp } from './components/ChatApp';

import './style.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const targetElement = document.getElementById(
		'wp-ai-demo-chat'
	);

	if ( targetElement ) {
		const root = createRoot( targetElement );
		root.render(
			<ConversationProvider>
				<ChatApp />
			</ConversationProvider>
		);
	} else {
		// eslint-disable-next-line no-console
		console.error( 'Target element #wp-ai-demo-chat not found.' );
	}
} );
