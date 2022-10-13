import React from 'react';
import { SketchPicker } from 'react-color';
import styled from 'styled-components';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

const ContainerBeforeDrop = styled.div`
  margin: 8px;
  border-radius: 2px;
`;

const ContainerBeforeDrag = styled.div`
  padding: 8px;
  transition: background-color 0.2s ease;
  background-color: ${props => (props.isDraggingOver ? 'skyblue' : 'white')};
  flex-grow: 1;

  display: flex;
  flex-wrap: wrap;
`;

const ContainerItem = styled.div`
  border: 1px solid lightgrey;
  border-radius: 2px;
  padding: 8px;
  margin-bottom: 8px;
  background-color: white;
  margin-right: 8px;
  justify-content: center;
  align-items: center;

  &:focus {
    outline: none;
    border-color: red;
  }
`;

class Label extends React.Component {
	constructor( props ) {
		super( props );

		this.state = {
			curr_list: this.props.curr_list ?? [],
			displayColorPicker: -1, // Show which label's color picker
		};

		this.onChange = this.onChange.bind( this );
		this.onChangeNew = this.onChangeNew.bind( this );
		this.togglePanelColorPicker = this.togglePanelColorPicker.bind( this );
		this.handleColorChange = this.handleColorChange.bind( this );
		this.onDragEnd = this.onDragEnd.bind( this );
	}

	onChange( i, value ) {
		var curr_list = this.state.curr_list;
		curr_list[ i ][ 0 ] = value;
		this.setState( { curr_list } );
	}

	onChangeNew() {
		var curr_list = this.state.curr_list;
		curr_list.push( [ '', '' ] );
		this.setState( { curr_list } );
	}

	togglePanelColorPicker( i ) {
		console.log( 'togglePanelColorPicker panel to ', i );
		this.setState({ displayColorPicker: this.state.displayColorPicker == i ? -1 : i })
	}

	handleColorChange( i, color ) {
		var curr_list = this.state.curr_list;
		curr_list[ i ][ 1 ] = color.hex;
		console.log( 'color changed to ', color.hex);
		this.setState( { curr_list } );
	}

	onDragEnd( res ) {
		const { destination, source, draggableId } = res;
		if ( ! destination ) {
			return;
		}

		if ( destination.droppableId === source.droppableId && destination.index === source.index ) {
			return;
		}

		const newItems = [...this.state.curr_list];
		const [removed] = newItems.splice( source.index, 1 );
		newItems.splice( destination.index, 0, removed );
		console.log( 'after dropped', newItems );
		this.setState( { curr_list: newItems } );
	}

	render() {
		return (
			<DragDropContext onDragEnd={this.onDragEnd}>
				<ContainerBeforeDrop>
					<Droppable droppableId="labelDropContainer" direction="horizontal">
						{ (provided, snapshot) => (
							<ContainerBeforeDrag ref={provided.innerRef} {...provided.droppableProps} isDraggingOver={snapshot.isDraggingOver}>
								{ this.state.curr_list.map( (item, i) => (
									<Draggable isDragDisabled={ this.state.displayColorPicker != -1 } draggableId={ 'drag'+i } index={ i } key={ i+1 }>
										{provided => (
											<ContainerItem ref={provided.innerRef} {...provided.draggableProps} {...provided.dragHandleProps}>
												<div className="existing_label">
													<div className="color_picker" style={ item[ 1 ] ? { backgroundColor: item[ 1 ] } : {} } onClick={ e => this.togglePanelColorPicker( i ) }></div>
													<input type="hidden" name="dropdown_colors[]" value={ item[ 1 ] ? item[ 1 ] : '' } />
													<input type="text" name="dropdown_vals[]" placeholder="Add Label" value={ item[ 0 ] } onChange={ e => this.onChange( i, e.target.value ) } />
												</div>
												{ this.state.displayColorPicker == i &&
													<div className="e_popover">
														<div className="e_wholecover" onClick={ e => this.togglePanelColorPicker( -1 ) } />
														<SketchPicker color={ item[ 1 ] ? item[ 1 ] : '' } onChange={ e => this.handleColorChange(i, e ) } />
													</div>
												}
											</ContainerItem>
										)}
									</Draggable>
								) ) }
								{provided.placeholder}
								<ContainerItem>
									<div className="new_label clickable" onClick={this.onChangeNew}><i className="fa fa-plus"></i></div>
								</ContainerItem>
							</ContainerBeforeDrag>
						)}
					</Droppable>
				</ContainerBeforeDrop>
			</DragDropContext>
		);
	}
}

export default Label;